<?php

namespace MediaWiki\Extension\ImportOfficeFiles;

use MediaWiki\Config\Config;
use MediaWiki\Json\FormatJson;
use MWCryptRand;
use SplFileInfo;

class Workspace {

	public const BUCKET_WORKSPACE = 'workspace';

	/**
	 * @var SplFileInfo
	 */
	private $file = null;

	/**
	 * @var string
	 */
	private $path;

	/**
	 * @var string
	 */
	private $dir;

	/**
	 * @var array
	 */
	private $buckets = [];

	/**
	 * @param Config $config
	 */
	public function __construct( $config ) {
		$this->dir = $config->get( 'UploadDirectory' );
	}

	/**
	 * @param string|false $id
	 * @param string|false $dir
	 * @return array
	 */
	public function init( $id = false, $dir = false ): array {
		if ( !$id ) {
			$id = MWCryptRand::generateHex( 6 );
		}
		if ( $dir !== false ) {
			$this->dir = $dir;
		}
		$this->path = $this->dir . '/' . $id;
		if ( !file_exists( "$this->path/workspace.json" ) ) {
			$status = wfMkdirParents( $this->path, null, get_class( $this ) );
			$this->addToBucket( 'workspace', [ 'id' => $id ] );
			$this->saveBucket( 'workspace' );
		}
		return $this->loadBucket( 'workspace' );
	}

	/**
	 * @param string $name
	 * @return string|null
	 */
	public function createSubDir( $name ): ?string {
		$status = wfMkdirParents( "$this->path/$name", null, get_class( $this ) );
		if ( !$status ) {
			return false;
		}
		return "$this->path/$name";
	}

	/**
	 * @return string
	 */
	public function getPath(): string {
		return $this->path;
	}

	/**
	 * @param SplFileInfo $file
	 * @return null|SplFileInfo
	 */
	public function uploadSourceFile( $file ) {
		$filename = $file->getFilename();
		$srcPath = $file->getPathname();
		$destPath = "$this->path/$filename";
		if ( !copy( $srcPath, $destPath ) ) {
			return null;
		}
		$this->file = new SplFileInfo( $destPath );
		$this->addToBucket(
			self::BUCKET_WORKSPACE,
			[
				'source' => $file->getFilename()
			]
		);
		$this->saveBucket( self::BUCKET_WORKSPACE );
		return $this->file;
	}

	/**
	 * @param string $name
	 * @param array $data
	 * @return void
	 */
	public function addToBucket( $name, $data ) {
		if ( !isset( $this->buckets[$name] ) ) {
			$this->buckets[$name] = $data;
		} else {
			$this->buckets[$name] = array_merge(
				$this->buckets[$name],
				$data
			);
		}
	}

	/**
	 * @param string $name
	 * @return void
	 */
	public function saveBucket( $name ) {
		if ( isset( $this->buckets[$name] ) ) {
			$path = "$this->path/$name.json";
			$data = FormatJson::encode( $this->buckets[$name] );
			file_put_contents( $path, $data );
		}
	}

	/**
	 * @param string $name
	 * @return array
	 */
	public function loadBucket( $name ): array {
		if ( isset( $this->buckets[$name] ) ) {
			return $this->buckets[$name];
		}
		$path = "$this->path/$name.json";
		$content = file_get_contents( $path );
		if ( !$content ) {
			$this->buckets[$name] = [];
			return [];
		} else {
			$this->buckets[$name] = FormatJson::decode( $content, true );
		}
		return $this->buckets[$name];
	}

	/**
	 * @return SplFileInfo|null
	 */
	public function getSourceFile(): ?SplFileInfo {
		$info = $this->loadBucket( self::BUCKET_WORKSPACE );
		if ( !$this->file && isset( $info['source'] ) ) {
			$filename = $info['source'];
			$this->file = new SplFileInfo( "$this->path/$filename" );
		}
		return $this->file;
	}
}
