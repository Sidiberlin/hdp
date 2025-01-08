<?php

namespace MediaWiki\Extension\PageCheckout;

use DateTime;
use MediaWiki\Extension\PageCheckout\Entity\CheckoutEntity;
use MediaWiki\Extension\PageCheckout\Entity\CheckoutEvent;
use MediaWiki\Extension\PageCheckout\Repo\CheckoutEventRepo;
use MediaWiki\Extension\PageCheckout\Repo\CheckoutRepo;
use Message;
use MWException;
use Title;
use User;

class CheckoutManager {
	/** @var User */
	private $actor;
	/** @var CheckoutRepo */
	private $checkoutRepo;
	/** @var CheckoutEventRepo */
	private $eventRepo;
	/** @var array */
	private $checkouts = [];
	/** @var SpecialLogLogger */
	private $specialLogLogger;

	/**
	 * @param User $actor
	 * @param CheckoutRepo $checkoutRepo
	 * @param CheckoutEventRepo $eventRepo
	 * @param SpecialLogLogger $logger
	 */
	public function __construct(
		User $actor, CheckoutRepo $checkoutRepo, CheckoutEventRepo $eventRepo, SpecialLogLogger $logger
	) {
		$this->actor = $actor;
		$this->checkoutRepo = $checkoutRepo;
		$this->eventRepo = $eventRepo;
		$this->specialLogLogger = $logger;
	}

	/**
	 * @param Title $title
	 * @param User $forUser
	 * @param array|null $payload Custom data to add to entity
	 * @return CheckoutEntity
	 * @throws MWException
	 */
	public function checkout( Title $title, User $forUser, $payload = [] ): CheckoutEntity {
		if ( !$title->exists() ) {
			throw new MWException( Message::newFromKey( 'pagecheckout-error-no-title' )->plain() );
		}
		if ( !$forUser->isRegistered() ) {
			throw new MWException( Message::newFromKey( 'pagecheckout-error-no-user' )->plain() );
		}
		if ( $this->isCheckedOut( $title ) ) {
			throw new MWException( Message::newFromKey( 'pagecheckout-error-has-checkout' )->plain() );
		}
		$entity = new CheckoutEntity( null, $title, $forUser, $payload );
		$entity = $this->checkoutRepo->save( $entity );
		if ( $entity instanceof CheckoutEntity ) {
			$this->recordEvent( $entity, 'checkout', $payload['comment'] ?? '' );
			$this->checkouts[$title->getArticleID()] = $entity;
			return $entity;
		}

		throw new MWException( Message::newFromKey( 'pagecheckout-error-save-failed' )->plain() );
	}

	/**
	 * @param Title $title
	 * @param string $comment
	 * @return bool
	 * @throws MWException
	 */
	public function checkIn( Title $title, $comment = '' ) {
		$entity = $this->checkoutRepo->getForPage( $title );
		if ( !$entity instanceof CheckoutEntity ) {
			throw new MWException( Message::newFromKey( 'pagecheckout-error-no-checkout' )->plain() );
		}
		$res = $this->checkoutRepo->delete( $entity );
		if ( $res ) {
			$this->recordEvent( $entity, 'checkin', $comment );
			unset( $this->checkouts[$title->getArticleID()] );
			return true;
		}

		return false;
	}

	/**
	 * @param Title $title
	 * @return bool
	 * @throws MWException
	 */
	public function clearCheckout( Title $title ) {
		$entity = $this->checkoutRepo->getForPage( $title );
		if ( !$entity instanceof CheckoutEntity ) {
			throw new MWException( Message::newFromKey( 'pagecheckout-error-no-checkout' )->plain() );
		}
		$res = $this->checkoutRepo->delete( $entity );
		if ( $res ) {
			$this->recordEvent( $entity, 'clear', '' );
			unset( $this->checkouts[$title->getArticleID()] );
			return true;
		}

		return false;
	}

	/**
	 * @param Title $title
	 * @return bool
	 */
	public function isCheckedOut( Title $title ): bool {
		return $this->getCheckoutEntity( $title ) instanceof CheckoutEntity;
	}

	/**
	 * @param Title $title
	 * @return CheckoutEntity|null
	 */
	public function getCheckoutEntity( Title $title ): ?CheckoutEntity {
		if ( !isset( $this->checkouts[$title->getArticleID()] ) ) {
			$this->checkouts[$title->getArticleID()] = $this->checkoutRepo->getForPage( $title );
		}

		return $this->checkouts[$title->getArticleID()];
	}

	/**
	 * @param CheckoutEntity $entity
	 * @param string $action
	 * @param string $comment
	 */
	private function recordEvent( CheckoutEntity $entity, $action, $comment ) {
		$event = new CheckoutEvent(
			$entity,
			$action,
			$this->actor,
			$entity->getTitle()->getLatestRevID(),
			new DateTime( 'now' ),
			$comment
		);

		$res = $this->eventRepo->save( $event );
		if ( !$res ) {
			throw new MWException( Message::newFromKey( 'pagecheckout-error-save-event' )->plain() );
		}

		$this->specialLogLogger->log( $entity, $this->actor, $action, $comment );
	}
}
