<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader\Component;

class Word2007Media {

	/**
	 * @param string $path
	 * @param Word2007DocumentData $documentData
	 * @return array
	 */
	public function execute( $path, Word2007DocumentData $documentData ): array {
		return [
			'filename-path' => $this->imagePath( $path ),
			'id-filename' => $this->imageRelations( $documentData->getRels() )
		];
	}

	/**
	 * @param string $path
	 * @return array
	 */
	private function imagePath( $path ): array {
		$mediaPath = $path . '/document/word/media';
		$namePath = [];
		if ( !is_dir( $mediaPath ) ) {
			return [];
		}
		$dir = opendir( $mediaPath );
		if ( $dir ) {
			$filename = readdir( $dir );
			while ( $filename !== false ) {
				if ( ( $filename === '.' ) || ( $filename === '..' ) ) {
					$filename = readdir( $dir );
					continue;
				}
				$namePath[$filename] = "$mediaPath/$filename";
				$filename = readdir( $dir );
			}
		}
		return $namePath;
	}

	/**
	 * @param array $rels
	 * @return void
	 */
	private function imageRelations( $rels ): array {
		$images = [];
		foreach ( $rels as $rel ) {
			if ( !isset( $rel['Id'] ) || !isset( $rel['Type'] ) || !isset( $rel['Target'] ) ) {
				continue;
			}

			$type = explode( '/', $rel['Type'] );
			$type = array_pop( $type );
			if ( $type === 'image' ) {
				$id = $rel['Id'];
				$name = explode( '/', $rel['Target'] );
				$images[$id] = array_pop( $name );
			}
		}
		return $images;
	}
}
