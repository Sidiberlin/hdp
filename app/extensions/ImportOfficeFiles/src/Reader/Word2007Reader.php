<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader;

use MediaWiki\Extension\ImportOfficeFiles\Reader\Component\Word2007Document;
use MediaWiki\Extension\ImportOfficeFiles\Reader\Component\Word2007DocumentData;
use MediaWiki\Extension\ImportOfficeFiles\Reader\Component\Word2007Media;
use MediaWiki\Extension\ImportOfficeFiles\Reader\Component\Word2007Numbering;
use MediaWiki\Extension\ImportOfficeFiles\Reader\Component\Word2007Rels;
use MediaWiki\Extension\ImportOfficeFiles\Reader\Component\Word2007Styles;
use SplFileInfo;
use ZipArchive;

class Word2007Reader {

	/**
	 * @var string|bool
	 */
	private $path;

	/**
	 * @var bool
	 */
	private $verbose;

	/**
	 * @param SplFileInfo $wordFile
	 * @param bool $verbose
	 * @return array|bool
	 */
	public function read( $wordFile, bool $verbose ): ?array {
		$this->path = $this->unzipFile( $wordFile );
		if ( !$this->path ) {
			return false;
		}

		$this->verbose = $verbose;

		$data = [
			'styles' => $this->getStyles(),
			'rels' => $this->getRels(),
			'media' => $this->getMedia(),
			'segments' => $this->getDocument()

		];
		return $data;
	}

	/**
	 * @param SplFileInfo $wordFile
	 * @return string|bool
	 */
	private function unzipFile( $wordFile ): ?string {
		$path = $wordFile->getPath();

		$zip = new ZipArchive();
		if ( $zip->open( $wordFile ) === true ) {
			$zip->extractTo( "$path/document" );
			$zip->close();
			return $path;
		}
		return false;
	}

	/**
	 * @return array
	 */
	private function getStyles(): array {
		$component = new Word2007Styles();
		$styles = $component->execute( $this->path );
		return $styles;
	}

	/**
	 * @return array
	 */
	private function getDocument(): array {
		$component = new Word2007Document();
		$documentData = new Word2007DocumentData(
			$this->getStyles(),
			$this->getRels(),
			$this->getNumbering()
		);
		$document = $component->execute(
			$this->path,
			$documentData,
			$this->verbose
		);
		return $document['segments'];
	}

	/**
	 * @return array
	 */
	private function getMedia(): array {
		$component = new Word2007Media();

		$documentData = new Word2007DocumentData(
			$this->getStyles(),
			$this->getRels(),
			$this->getNumbering()
		);

		$media = $component->execute( $this->path, $documentData );
		return $media;
	}

	/**
	 * @return array
	 */
	private function getRels(): array {
		$component = new Word2007Rels();
		$rels = $component->execute( $this->path );
		return $rels;
	}

	/**
	 * @return Word2007Numbering
	 * @see \MediaWiki\Extension\ImportOfficeFiles\Reader\Component\Word2007Numbering
	 */
	private function getNumbering(): Word2007Numbering {
		$numbering = new Word2007Numbering();
		$numbering->parseData( $this->path );
		return $numbering;
	}
}
