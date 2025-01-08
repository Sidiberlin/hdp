<?php

namespace BlueSpice\Bookshelf\HookHandler;

use BlueSpice\Bookshelf\Hook\BSBookshelfPageAddedToBookHook;
use BlueSpice\Bookshelf\Hook\BSBookshelfPageRemovedFromBookHook;
use BS\ExtendedSearch\Source\Job\UpdateWikiPage;
use ExtensionRegistry;
use JobQueueGroup;
use Title;

class UpdateSearchIndex implements BSBookshelfPageAddedToBookHook, BSBookshelfPageRemovedFromBookHook {

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
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'BlueSpiceExtendedSearch' ) ) {
			return;
		}
		$this->jobQueueGroup->push( new UpdateWikiPage( $page ) );
	}

	/**
	 * @inheritDoc
	 */
	public function onBSBookshelfPageRemovedFromBook( Title $book, Title $page ): void {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'BlueSpiceExtendedSearch' ) ) {
			return;
		}
		$this->jobQueueGroup->push( new UpdateWikiPage( $page ) );
	}
}
