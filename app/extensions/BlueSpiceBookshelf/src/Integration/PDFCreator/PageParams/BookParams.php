<?php

namespace BlueSpice\Bookshelf\Integration\PDFCreator\PageParams;

use BlueSpice\Bookshelf\BookMetaLookup;
use BlueSpice\Bookshelf\IMetaDataDescription;
use MediaWiki\Extension\PDFCreator\IPageParamsProvider;
use MediaWiki\Extension\PDFCreator\Utility\ParamDesc;
use MediaWiki\Message\Message;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserIdentity;
use MWStake\MediaWiki\Component\ManifestRegistry\ManifestAttributeBasedRegistry;
use Wikimedia\ObjectFactory\ObjectFactory;

class BookParams implements IPageParamsProvider {

	/** @var TitleFactory */
	private $titleFactory;

	/** @var BookMetaLookup */
	private $bookMetaLookup;

	/** @var ObjectFactory */
	private $objectFactory;

	/** @var array */
	private $skipParams = [
		'bookshelfimage',
		'pdftemplate'
	];

	/**
	 * @param TitleFactory $titleFactory
	 * @param BookMetaLookup $bookMetaLookup
	 * @param ObjectFactory $objectFactory
	 */
	public function __construct(
		TitleFactory $titleFactory, BookMetaLookup $bookMetaLookup,
		ObjectFactory $objectFactory
	) {
		$this->titleFactory = $titleFactory;
		$this->bookMetaLookup = $bookMetaLookup;
		$this->objectFactory = $objectFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function execute( ?PageIdentity $pageIdentity, ?UserIdentity $userIdentity ): array {
		if ( $pageIdentity === null ) {
			return [];
		}
		$title = $this->titleFactory->newFromPageIdentity( $pageIdentity );
		if ( $title->getNamespace() !== NS_BOOK ) {
			return [];
		}
		$bookMeta = $this->bookMetaLookup->getMetaForBook( $title );

		$params = [];
		foreach ( $bookMeta as $key => $item ) {
			if ( $key === 'docummenttype' ) {
				$key = 'documenttype';
			}
			if ( $key === 'identifier' ) {
				$key = 'documentidentifier';
			}
			$params['book-' . $key ] = $item;
		}

		return $params;
	}

	/**
	 * @inheritDoc
	 */
	public function getParamsDescription( $languageCode ): array {
		$registry = new ManifestAttributeBasedRegistry(
			'BlueSpiceBookshelfMetaData'
		);

		$desc = [];
		$data = $registry->getAllValues();
		foreach ( $data as $key => $spec ) {
			$object = $this->objectFactory->createObject( $registry->getObjectSpec( $key ) );
			if ( !( $object instanceof IMetaDataDescription ) ) {
				continue;
			}
			if ( in_array( $object->getKey(), $this->skipParams, true ) ) {
				continue;
			}

			$key = 'book-' . $object->getKey();
			if ( $object->getKey() === 'docummenttype' ) {
				$key = 'book-documenttype';
			}
			if ( $object->getKey() === 'docummentidentifier' ) {
				$key = 'book-documentidentifier';
			}

			$desc[] = new ParamDesc(
				$key,
				Message::newFromKey( 'bs-bookshelf-export-pageparam-desc-' . $object->getKey() ),
				$this->getParamsExample( $key, $languageCode )
			);
		}
		return $desc;
	}

	/**
	 * @param string $key
	 * @param string $languageCode
	 * @return string
	 */
	private function getParamsExample( $key, $languageCode ) {
		$examples = [
			'book-title' => [
				'en' => 'Manual',
				'de' => 'Handbuch',
			],
			 'book-subtitle' => [
				'en' => 'Subtitle',
				'de' => 'Untertitel',
			],
			'book-author1' => [
				'en' => 'John Doe',
				'de' => 'Max Mustermann',
			],
			'book-author2' => [
				'en' => 'John Doe',
				'de' => 'Max Mustermann',
			],
			'book-department' => [
				'en' => 'Technical',
				'de' => 'Interne ',
			],
			'book-bookshelf' => [
				'en' => 'Instructions',
				'de' => 'Anweisungen',
			],
			'book-documenttype' => [
				'en' => 'pdf',
				'de' => 'pdf'
			],
			'book-documentidentifier' => [
				'en' => 'M-123'
			],
			'book-version' => [
				'en' => '2.0.0'
			]
		];
		if ( $languageCode === 'de' || $languageCode === 'de_formal' ) {
			if ( isset( $examples[$key]['de'] ) ) {
				return $examples[$key]['de'];
			}
		}
		if ( isset( $examples[$key]['en'] ) ) {
			return $examples[$key]['en'];
		}
		return '';
	}
}
