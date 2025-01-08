<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Converter;

use MediaWiki\Extension\ImportOfficeFiles\BookmarkResolver;
use MediaWiki\Extension\ImportOfficeFiles\CollectionBuilder;
use MediaWiki\Extension\ImportOfficeFiles\ConverterResult;
use MediaWiki\Extension\ImportOfficeFiles\IConverter;
use MediaWiki\Extension\ImportOfficeFiles\ImportXmlBuilder;
use MediaWiki\Extension\ImportOfficeFiles\Modules\MSOfficeWord;
use MediaWiki\Extension\ImportOfficeFiles\RemoveHeading;
use MediaWiki\Extension\ImportOfficeFiles\Segment;
use MediaWiki\Extension\ImportOfficeFiles\SegmentList;
use MediaWiki\Extension\ImportOfficeFiles\WikiTextProcessor\BoldMarkupReplacement;
use MediaWiki\Extension\ImportOfficeFiles\WikiTextProcessor\ImageReplacement;
use MediaWiki\Extension\ImportOfficeFiles\WikiTextProcessor\ItalicMarkupReplacement;
use MediaWiki\Extension\ImportOfficeFiles\WikiTextProcessor\RemoveIllegalMarkup;
use MediaWiki\Extension\ImportOfficeFiles\Word2007ConverterParams;
use MediaWiki\Extension\ImportOfficeFiles\Workspace;

class MSOfficeWordConverter implements IConverter {

	public const DIR_RESULT = 'result';
	public const DIR_IMAGES = 'result/images';

	/**
	 * @var Workspace
	 */
	private $workspace;

	/**
	 * @var string
	 */
	private $resultDir = '';

	/**
	 * @var bool
	 */
	private $verbose;

	/**
	 * @var string
	 */
	private $username = '';

	/**
	 * @param Workspace $workspace
	 * @return ConverterResult
	 */
	public function convert( $workspace ): ConverterResult {
		$params = new Word2007ConverterParams(
				$workspace->loadBucket( MSOfficeWord::BUCKET_CONVERTER_PARAMS )
		);
		$this->workspace = $workspace;
		$this->namespace = $params->getNamespace();
		$this->categories = $params->getCategories();
		$this->verbose = $params->getVerbose();
		$this->username = $params->getUsername();

		$this->resultDir = $this->workspace->createSubDir( self::DIR_RESULT );

		$converted = $this->convertSegments();

		for ( $index = 0; $index < $converted->count(); $index++ ) {
			$this->workspace->addToBucket(
				MSOfficeWord::BUCKET_CONVERTER,
				[
					$converted->item( $index )->getLevel(),
					$converted->item( $index )->getLabel(),
					$converted->item( $index )->getFilePath()
				]
			);
		}

		$mediaDir = $this->extractMedia();
		$fileExtensions = $this->workspace->loadBucket( MSOfficeWord::BUCKET_MEDIA_FILE_EXTENSIONS );
		file_put_contents( "$this->resultDir/extensions.txt", implode( ',', $fileExtensions ) );

		$this->saveXML( $converted );

		$converterData = [
			'file' => $this->resultDir . '/import.xml',
			'images' => $mediaDir,
			'file-extensions' => $fileExtensions
		];

		$this->workspace->addToBucket(
			MSOfficeWord::BUCKET_CONVERTER,
			$converterData
		);
		$this->workspace->saveBucket( MSOfficeWord::BUCKET_CONVERTER );

		$collectionBulder = new CollectionBuilder();
		$collection = $collectionBulder->build( $converted );
		file_put_contents( "$this->resultDir/collection.wikitext", $collection );

		return new ConverterResult(
			true,
			$converterData['file'],
			$converterData['images'],
			$fileExtensions,
			$collection
		);
	}

	/**
	 * @return SegmentList
	 */
	private function loadSegmentsFromBucket(): SegmentList {
		$segmentsBucket = $this->workspace->loadBucket( MSOfficeWord::BUCKET_SEGMENTS );
		$segmentList = new SegmentList();
		foreach ( $segmentsBucket as $segment ) {
			$segmentList->add( new Segment(
				$segment['level'],
				$segment['label'],
				$segment['file']
			) );
		}
		return $segmentList;
	}

	/**
	 * @return array
	 */
	private function getWikiTextProcessors(): array {
		return [
			new ImageReplacement( $this->workspace ),
			new BoldMarkupReplacement(),
			new ItalicMarkupReplacement(),
			new RemoveIllegalMarkup()
		];
	}

	/**
	 * @param string $wikiText
	 * @return string
	 */
	private function runWikiTextProcessors( $wikiText ): string {
		$processors = $this->getWikiTextProcessors();
		foreach ( $processors as $processor ) {
			$wikiText = $processor->process( $wikiText );
		}
		return $wikiText;
	}

	/**
	 * @param string $level
	 * @param string $heading
	 * @param string $wikiText
	 * @return string
	 */
	private function removeHeading( $level, $heading, $wikiText ): string {
		$removeHeading = new RemoveHeading();
		$wikiText = $removeHeading->execute( $level, $heading, $wikiText );
		return $wikiText;
	}

	/**
	 * @return SegmentList
	 */
	private function convertSegments(): SegmentList {
		$convertedDir = $this->workspace->createSubDir( 'converted' );
		$wikiText = '';
		$segmentList = $this->loadSegmentsFromBucket();
		$converterSegments = new SegmentList();
		$count = 1;
		for ( $index = 0; $index < $segmentList->count(); $index++ ) {

			$rawWikiText = file_get_contents( $segmentList->item( $index )->getFilePath() );
			$wikiText = $this->runWikiTextProcessors( $rawWikiText );
			$wikiText = $this->removeHeading(
				$segmentList->item( $index )->getLevel(),
				$segmentList->item( $index )->getLabel(),
				$wikiText
			);
			$wikiText .= $this->buildCategories();

			file_put_contents( "$convertedDir/part-$count.wikitext", $wikiText );

			/** Strip markup placeholder */
			$label = $segmentList->item( $index )->getLabel();
			$label = preg_replace( '/###(.*?)###/', '', $label );

			$converterSegments->add( new Segment(
				$segmentList->item( $index )->getLevel(),
				$label,
				"$convertedDir/part-$count.wikitext"
			) );

			$this->workspace->addToBucket(
				MSOfficeWord::BUCKET_CONVERTED_TITLE_FILEPATH,
				[
					'level' => $segmentList->item( $index )->getLevel(),
					'title' => $label,
					'file' => "$convertedDir/part-$count.wikitext"
				]
			);

			$count++;
		}
		$this->workspace->saveBucket( MSOfficeWord::BUCKET_CONVERTED_TITLE_FILEPATH );

		$bookmarkResolver = new BookmarkResolver();
		$convertedSegments = $bookmarkResolver->resolve( $converterSegments );
		return $convertedSegments;
	}

	/**
	 * @return string
	 */
	private function buildCategories(): string {
		$wikiCategories = "";
		if ( !empty( $this->categories ) ) {
			$wikiCategories = "\n";
		}
		foreach ( $this->categories as $category ) {
			$wikiCategories .= "[[Category:$category]]\n";
		}
		return $wikiCategories;
	}

	/**
	 * @param SegmentList $converted
	 * @return void
	 */
	private function saveXML( $converted ) {
		$pageXmls = [];
		$importXmlBuilder = new ImportXmlBuilder();
		for ( $index = 0; $index < $converted->count(); $index++ ) {
			$title = $converted->item( $index )->getLabel();
			$text = file_get_contents( $converted->item( $index )->getFilePath() );
			$pageXmls[] = $importXmlBuilder->buildPageXml( $title, $text, $this->username );
		}
		$importXml = $importXmlBuilder->buildImportXml( $pageXmls );
		file_put_contents( "$this->resultDir/import.xml", $importXml );
	}

	/**
	 * @return string
	 */
	private function extractMedia(): string {
		$filenamePath = $this->workspace->loadBucket( MSOfficeWord::BUCKET_MEDIA_FILENAME_FILEPATH );
		$mediaMap = $this->workspace->loadBucket( MSOfficeWord::BUCKET_CONVERTER_MEDIA_MAP );
		$mediaDir = $this->workspace->createSubDir( self::DIR_IMAGES );

		if ( $this->verbose ) {
			echo "\nExtracting images ";
		}
		$importFilenameFilePaths = [];
		foreach ( $mediaMap as $filename => $destName ) {
			if ( !isset( $filenamePath[$filename] ) ) {
				continue;
			}
			if ( $this->verbose ) {
				echo ".";
			}
			copy( $filenamePath[$filename], "$mediaDir/$destName" );
			$importFilenameFilePaths[$filename][] = "$mediaDir/$destName";
		}
		if ( $this->verbose ) {
			echo " done\n";
		}
		$this->workspace->saveBucket(
			MSOfficeWord::BUCKET_IMPORT_MEDIA_FILENAME_FILEPATH,
			$importFilenameFilePaths
		);

		return $mediaDir;
	}
}
