<?php

namespace MediaWiki\Extension\ContentProvisioning\Data;

use Language;
use MediaWiki\Languages\LanguageFallback;
use MediaWiki\Page\WikiPageFactory;
use MWStake\MediaWiki\Component\DataStore\IStore;
use TitleFactory;
use Wikimedia\Rdbms\ILoadBalancer;

class Store implements IStore {

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
		$this->wikiPageFactory = $wikiPageFactory;
		$this->titleFactory = $titleFactory;
		$this->wikiLang = $wikiLang;
		$this->languageFallback = $languageFallback;
	}

	/**
	 * @inheritDoc
	 */
	public function getWriter() {
		return null;
	}

	/**
	 * @inheritDoc
	 */
	public function getReader() {
		return new Reader(
			$this->wikiPageFactory,
			$this->titleFactory,
			$this->wikiLang,
			$this->languageFallback
		);
	}
}
