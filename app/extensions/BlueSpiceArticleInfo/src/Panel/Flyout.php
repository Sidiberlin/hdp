<?php

namespace BlueSpice\ArticleInfo\Panel;

use MediaWiki\MediaWikiServices;
use Message;
use QuickTemplate;
use WikiPage;

class Flyout {
	/**
	 * RL modules that are added by other extensions
	 * wanting to show their info in this flyout
	 *
	 * @var array
	 */
	protected $modulesToLoad = [];

	/**
	 * Callback function to allow other extesions to
	 * insert content
	 *
	 * @var array
	 */
	protected $makeItemsCallbacks = [];

	/**
	 * @var \Title
	 */
	protected $title;

	/**
	 * @var \MediaWiki\Revision\RevisionStore
	 */
	protected $revisionStore;

	/**
	 * @var \Article
	 */
	protected $article;

	/**
	 * @var WikiPage
	 */
	protected $wikiPage;

	/** @var QuickTemplate */
	protected $skintemplate = null;

	/** @var MediaWikiServices */
	protected $services = null;

	/**
	 *
	 * @param QuickTemplate $skintemplate
	 */
	public function __construct( QuickTemplate $skintemplate ) {
		$this->skintemplate = $skintemplate;
		$this->setForeignModules();
		$this->title = $skintemplate->getSkin()->getTitle();
		$this->services = MediaWikiServices::getInstance();
	}

	/**
	 * @return Message
	 */
	public function getFlyoutTitleMessage() {
		return $this->skintemplate->getSkin()->msg( 'bs-articleinfo-flyout-title' );
	}

	/**
	 * @return Message
	 */
	public function getFlyoutIntroMessage() {
		return $this->skintemplate->getSkin()->msg( 'bs-articleinfo-flyout-intro' );
	}

	/**
	 * @return Message
	 */
	public function getTitleMessage() {
		return $this->skintemplate->getSkin()->msg( 'bs-articleinfo-nav-link-title' );
	}

	/**
	 * @return string
	 */
	public function getBody() {
		return '';
	}

	/**
	 *
	 * @return array
	 */
	public function getContainerData() {
		$data = [
			'make-items-callbacks' => \FormatJson::encode( $this->makeItemsCallbacks ),
			'rl-module-dependencies' => \FormatJson::encode( $this->modulesToLoad )
		];

		$lastEditedTime = $this->getLastEditedTime();
		if ( $lastEditedTime ) {
			$data['last-edited-time'] = $lastEditedTime;
		}

		$lastEditor = $this->getLastEditedUser();
		if ( $lastEditor ) {
			$data['last-edited-user'] = $lastEditor;
		}

		$skin = $this->skintemplate->getSkin();
		$data['has-subpages'] = $skin->getTitle()->hasSubpages();

		$data['user-can-edit'] = $this->services->getPermissionManager()
			->userCan( 'edit', $skin->getUser(), $skin->getTitle() );

		return $data;
	}

	/**
	 *
	 * @return string
	 */
	public function getTriggerCallbackFunctionName() {
		return 'bs.articleinfo.flyoutCallback';
	}

	/**
	 *
	 * @return array
	 */
	public function getTriggerRLDependencies() {
		return [
			'ext.bluespice.articleinfo.flyout',
			'ext.bluespice.articleinfo.flyout.styles'
		];
	}

	/**
	 *
	 */
	protected function setForeignModules() {
		$flyoutModuleRegistry = \ExtensionRegistry::getInstance()
			->getAttribute( 'BlueSpiceArticleInfoFlyoutModules' );

		foreach ( $flyoutModuleRegistry as $key => $module ) {
			if ( isset( $module['skip-callback'] ) && is_callable( $module['skip-callback'] ) ) {
				$result = call_user_func_array(
					$module['skip-callback'],
					[ $this->skintemplate->getSkin()->getContext() ]
				);

				if ( !$result ) {
					continue;
				}
			}

			$this->modulesToLoad[] = $module['module'];
			$this->makeItemsCallbacks[] = $module['make-items-callback'];
		}
	}

	/**
	 *
	 * @return \MediaWiki\Revision\RevisionStore
	 */
	protected function getRevisionStore() {
		if ( $this->revisionStore === null ) {
			$this->revisionStore = $this->services->getRevisionStore();
		}
		return $this->revisionStore;
	}

	/**
	 * DEPRECATED
	 * @deprecated since version 3.3.0 - use $this->getWikiPage() instead
	 * @return \Article
	 */
	protected function getArticle() {
		wfDebugLog( 'bluespice-deprecations', __METHOD__, 'private' );
		if ( $this->article === null ) {
			$this->article = \Article::newFromID( $this->title->getArticleID() );
		}
		return $this->article;
	}

	/**
	 *
	 * @return WikiPage
	 */
	protected function getWikiPage() {
		if ( $this->wikiPage === null ) {
			$this->wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $this->title );
		}
		return $this->wikiPage;
	}

	/**
	 * Gets info on last time page was edited
	 *
	 * @return string|false if cannot be retrieved
	 */
	protected function getLastEditedTime() {
		$article = $this->getWikiPage();
		$oldId = $this->skintemplate->getSkin()->getRequest()->getInt( 'oldid', 0 );

		if ( !$article instanceof WikiPage ) {
			return false;
		}

		if ( $oldId != 0 ) {
			$this->getRevisionStore();
			$timestamp = $this->revisionStore->getTimestampFromId( $article->getTitle(), $oldId );
		} else {
			$timestamp = $article->getTimestamp();
		}

		$formattedTimestamp = ( new \BlueSpice\Timestamp( $timestamp ) )->getAgeString();
		$articleHistoryLinkURL = $article->getTitle()->getLinkURL(
			[
				'diff'   => 0,
				'oldid' => $oldId
			]
		);

		return \FormatJson::encode( [
			'timestamp' => $formattedTimestamp,
			'url' => $articleHistoryLinkURL
		] );
	}

	/**
	 * Gets info on last editor of the page
	 *
	 * @return string|false if cannot be retrieved
	 */
	protected function getLastEditedUser() {
		$article = $this->getWikiPage();
		if ( !$article instanceof WikiPage ) {
			return false;
		}

		$userText = $article->getUserText();
		if ( $userText == '' ) {
			return false;
		}

		$lastEditor = $this->services->getUserFactory()->newFromName( $userText );
		if ( $lastEditor instanceof \User === false ) {
			return false;
		}

		$userHelper = $this->services->getService( 'BSUtilityFactory' )
			->getUserHelper( $lastEditor );

		return \FormatJson::encode( [
			'userText' => $userHelper->getDisplayName(),
			'url' => $lastEditor->getUserPage()->getFullURL()
		] );
	}
}
