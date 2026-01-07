<?php

namespace BlueSpice\Bookshelf\HookHandler;

use BlueSpice\Bookshelf\Hook\BSBookshelfPageAddedToBookHook;
use BlueSpice\Bookshelf\Hook\BSBookshelfPageRemovedFromBookHook;
use BS\ExtendedSearch\Source\Job\UpdateWikiPage;
use JobQueueGroup;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Title\Title;
use SMW\MediaWiki\Jobs\UpdateJob;

class QueueJobs implements BSBookshelfPageAddedToBookHook, BSBookshelfPageRemovedFromBookHook {

	/** @var JobQueueGroup */
	private $jobQueueGroup;

	/**
	 * @param JobQueueGroup $jobQueueGroup
	 */
	public function __construct( JobQueueGroup $jobQueueGroup ) {
		$this->jobQueueGroup = $jobQueueGroup;
	}

	/**
	 * @inheritDoc
	 */
	public function onBSBookshelfPageAddedToBook( Title $book, Title $page ): void {
		if ( ExtensionRegistry::getInstance()->isLoaded( 'BlueSpiceExtendedSearch' ) ) {
			$this->jobQueueGroup->push( new UpdateWikiPage( $page ) );
		}
		if ( ExtensionRegistry::getInstance()->isLoaded( 'BlueSpiceSMWConnector' ) ) {
			$this->jobQueueGroup->push( new UpdateJob( $page ) );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onBSBookshelfPageRemovedFromBook( Title $book, Title $page ): void {
		if ( ExtensionRegistry::getInstance()->isLoaded( 'BlueSpiceExtendedSearch' ) ) {
			$this->jobQueueGroup->push( new UpdateWikiPage( $page ) );
		}
		if ( ExtensionRegistry::getInstance()->isLoaded( 'BlueSpiceSMWConnector' ) ) {
			$this->jobQueueGroup->push( new UpdateJob( $page ) );
		}
	}
}
