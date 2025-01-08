<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Analyzer;

use MediaWiki\Extension\ImportOfficeFiles\AnalyzerResult;
use MediaWiki\Extension\ImportOfficeFiles\IAnalyzer;
use MediaWiki\Extension\ImportOfficeFiles\Modules\MSOfficeWord;
use MediaWiki\Extension\ImportOfficeFiles\Reader\Word2007Reader;
use MediaWiki\Extension\ImportOfficeFiles\SegmentList;
use MediaWiki\Extension\ImportOfficeFiles\SegmentsTitleBuilder;
use MediaWiki\Extension\ImportOfficeFiles\WikiPageStructureBuilder;
use MediaWiki\Extension\ImportOfficeFiles\Word2007AnalyzerParams;
use MediaWiki\Extension\ImportOfficeFiles\Workspace;
use SplFileInfo;

class MSOfficeWordAnalyzer implements IAnalyzer {

	/**
	 * @var array
	 */
	private $params;

	/**
	 * @var string
	 */
	private $baseTitle;

	/**
	 * @var string
	 */
	private $namespace;

	/**
	 * @var array
	 */
	private $documentData = [];

	/**
	 * @var SplFileInfo
	 */
	private $file = null;

	/**
	 * @var Workspace
	 */
	private $workspace;

	/**
	 * @var int
	 */
	private $split;

	/**
	 * @var array
	 */
	private $categories = [];

	/**
	 * @param Workspace $workspace
	 * @return AnalyzerResult
	 */
	public function analyze( $workspace ): AnalyzerResult {
		$this->workspace = $workspace;
		$this->params = $this->workspace->loadBucket( MSOfficeWord::BUCKET_ANALYZER_PARAMS );
		$analyzerParams = new Word2007AnalyzerParams( $this->params );

		$this->baseTitle = $analyzerParams->getBaseTitle();
		$this->file = $workspace->getSourceFile();
		if ( empty( $this->baseTitle ) ) {
			$this->baseTitle = $this->file->getFilename();
		}
		$status = false;

		$verbose = $analyzerParams->getVerbose();

		$wordReader = new Word2007Reader();
		$this->documentData = $wordReader->read( $this->file, $verbose );

		$this->saveBucket(
			MSOfficeWord::BUCKET_STYLES,
			$this->documentData['styles']
		);
		$this->saveBucket(
			MSOfficeWord::BUCKET_RELATIONS,
			$this->documentData['rels']
		);
		$fileExtensions = [];
		foreach ( $this->documentData['media']['filename-path'] as $path ) {
			$file = new SplFileInfo( $path );
			$fileExtensions[] = $file->getExtension();
		}
		$fileExtensions = array_unique( $fileExtensions );
		$this->saveBucket(
			MSOfficeWord::BUCKET_MEDIA_FILE_EXTENSIONS,
			$fileExtensions
		);

		$this->saveBucket(
			MSOfficeWord::BUCKET_MEDIA_FILENAME_FILEPATH,
			$this->documentData['media']['filename-path']
		);
		$this->saveBucket(
			MSOfficeWord::BUCKET_MEDIA_ID_FILENAME,
			$this->documentData['media']['id-filename']
		);

		/** build segmentlist for array of segments */
		$segmentList = new SegmentList();
		foreach ( $this->documentData['segments'] as $segment ) {
			$segmentList->add( $segment );
		}

		/** buld page structure */
		$analyzeDir = $this->workspace->createSubDir( 'prep' );
		$wikiPageStructureBuilder = new WikiPageStructureBuilder();
		$wikiPageSegments = $wikiPageStructureBuilder->build(
			$analyzeDir,
			$segmentList,
			$analyzerParams->getSplit()
		);

		/** build title map for analyzerSegmentes */
		$titleSegmentsBuilder = new SegmentsTitleBuilder(
			$wikiPageSegments,
			$this->baseTitle,
			$analyzerParams->getNamespace(),
			$analyzerParams->getUncollideTitles()
		);

		$titleMap = $titleSegmentsBuilder->buildTitleMap();
		$this->saveBucket(
			MSOfficeWord::BUCKET_TITLE_MAP,
			$titleMap
		);

		$titleSegments = $titleSegmentsBuilder->buildSegmentList();
		for ( $index = 0; $index < $titleSegments->count(); $index++ ) {
			$this->workspace->addToBucket(
				MSOfficeWord::BUCKET_SEGMENTS,
				[
					[
						'level' => $titleSegments->item( $index )->getLevel(),
						'label' => $titleSegments->item( $index )->getLabel(),
						'file' => $titleSegments->item( $index )->getFilePath()
					]
				]
			);
			if ( $verbose ) {
				echo $titleSegments->item( $index )->getLabel() . "\n";
			}
		}
		$this->workspace->saveBucket( MSOfficeWord::BUCKET_SEGMENTS );

		$result = [
			'filename' => $this->file->getFilename(),
			'namespace' => $analyzerParams->getNamespace(),
			'base-title' => $titleMap[0],
			'split' => $analyzerParams->getSplit(),
			'categories' => $analyzerParams->getCategories(),
			'ns-filerepo-compat' => $analyzerParams->getNsFileRepoCompat(),
			'title-map' => $titleMap
		];

		$this->saveBucket(
			MSOfficeWord::BUCKET_ANALYZER,
			$result
		);

		$status = true;
		return new AnalyzerResult(
			$status,
			$this->file->getFilename(),
			$analyzerParams->getNamespace(),
			$this->baseTitle,
			$analyzerParams->getSplit(),
			$analyzerParams->getCategories(),
			$titleMap,
			$fileExtensions,
			$analyzerParams->getNsFileRepoCompat()
		);
	}

	/**
	 * @param string $name
	 * @param string $data
	 * @return void
	 */
	private function saveBucket( $name, $data ) {
		$this->workspace->addToBucket( $name, $data );
		$this->workspace->saveBucket( $name );
	}
}
