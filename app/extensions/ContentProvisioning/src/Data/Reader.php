<?php

namespace MediaWiki\Extension\ContentProvisioning\Data;

use MediaWiki\Language\Language;
use MediaWiki\Languages\LanguageFallback;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Title\TitleFactory;

class Reader extends \MWStake\MediaWiki\Component\DataStore\Reader {

	/**
	 * @var WikiPageFactory
	 */
	private $wikiPageFactory;

	/**
	 * @var TitleFactory
	 */
	private $titleFactory;

	/**
	 * @var Language
	 */
	private $wikiLang;

	/**
	 * @var LanguageFallback
	 */
	private $languageFallback;

	/**
	 * @param WikiPageFactory $wikiPageFactory
	 * @param TitleFactory $titleFactory
	 * @param Language $wikiLang
	 * @param LanguageFallback $languageFallback
	 */
	public function __construct(
		WikiPageFactory $wikiPageFactory,
		TitleFactory $titleFactory,
		Language $wikiLang,
		LanguageFallback $languageFallback
	) {
		parent::__construct();
		$this->wikiPageFactory = $wikiPageFactory;
		$this->titleFactory = $titleFactory;
		$this->wikiLang = $wikiLang;
		$this->languageFallback = $languageFallback;
	}

	/**
	 * @inheritDoc
	 */
	public function getSchema() {
		return new Schema();
	}

	/**
	 * @inheritDoc
	 */
	protected function makePrimaryDataProvider( $params ) {
		return new PrimaryDataProvider(
			$this->wikiPageFactory,
			$this->titleFactory,
			$this->wikiLang,
			$this->languageFallback
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function makeSecondaryDataProvider() {
		return new SecondaryDataProvider();
	}
}
