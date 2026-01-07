<?php

namespace BlueSpice\Bookshelf\Notifications\SubscriptionSet;

use BlueSpice\Bookshelf\BookLookup;
use MediaWiki\Extension\NotifyMe\SubscriberProvider\ManualProvider\ISubscriptionSet;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserIdentity;
use MWStake\MediaWiki\Component\Events\INotificationEvent;
use MWStake\MediaWiki\Component\Events\TitleEvent;

class Book implements ISubscriptionSet {
	/** @var BookLookup */
	private $bookLookup;

	/** @var TitleFactory */
	private $titleFactory;

	/**
	 * @param BookLookup $bookLookup
	 * @param TitleFactory $titleFactory
	 */
	public function __construct( BookLookup $bookLookup, TitleFactory $titleFactory ) {
		$this->titleFactory = $titleFactory;
		$this->bookLookup = $bookLookup;
	}

	/**
	 * @inheritDoc
	 */
	public function isSubscribed( array $setData, INotificationEvent $event, UserIdentity $user ): bool {
		if ( !( $event instanceof TitleEvent ) ) {
			return false;
		}
		$book = $setData['book'] ?? null;
		if ( !$book ) {
			return false;
		}
		$requestedBookTitle = $this->titleFactory->makeTitleSafe( NS_BOOK, $book );
		if ( !$requestedBookTitle || !$requestedBookTitle->exists() ) {
			return false;
		}
		$books = array_keys( $this->bookLookup->getBooksForPage( $event->getTitle() ) );
		return in_array( $requestedBookTitle->getPrefixedDBkey(), $books );
	}

	/**
	 * @inheritDoc
	 */
	public function getClientSideModule(): string {
		return "ext.bluespice.bookshelf.notificationsSubscriptionSet";
	}
}
