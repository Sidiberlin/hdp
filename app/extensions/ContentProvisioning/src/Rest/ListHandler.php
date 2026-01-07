<?php

namespace MediaWiki\Extension\ContentProvisioning\Rest;

use MediaWiki\Extension\ContentProvisioning\Data\Store;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Language\Language;
use MediaWiki\Languages\LanguageFallback;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Title\TitleFactory;
use MWStake\MediaWiki\Component\CommonWebAPIs\Rest\QueryStore;
use MWStake\MediaWiki\Component\DataStore\IStore;

class ListHandler extends QueryStore {

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
	 * @param HookContainer $hookContainer
	 * @param WikiPageFactory $wikiPageFactory
	 * @param TitleFactory $titleFactory
	 * @param Language $wikiLang
	 * @param LanguageFallback $languageFallback
	 */
	public function __construct(
		HookContainer $hookContainer,
		WikiPageFactory $wikiPageFactory,
		TitleFactory $titleFactory,
		Language $wikiLang,
		LanguageFallback $languageFallback
	) {
		parent::__construct( $hookContainer );
		$this->wikiPageFactory = $wikiPageFactory;
		$this->titleFactory = $titleFactory;
		$this->wikiLang = $wikiLang;
		$this->languageFallback = $languageFallback;
	}

	/**
	 * @inheritDoc
	 */
	protected function getStore(): IStore {
		return new Store(
			$this->wikiPageFactory,
			$this->titleFactory,
			$this->wikiLang,
			$this->languageFallback
		);
	}
}
