<?php

namespace MediaWiki\Extension\PageCheckout\AlertProvider;

use MediaWiki\Extension\PageCheckout\CheckoutManager;
use MWStake\MediaWiki\Component\AlertBanners\IAlertProvider;
use RequestContext;

class PageCheckout implements IAlertProvider {

	/** @var CheckoutManager */
	private $manager;

	/**
	 * ApplyCheckout constructor.
	 * @param CheckoutManager $manager
	 */
	public function __construct( CheckoutManager $manager ) {
		$this->manager = $manager;
	}

	/**
	 * @return string
	 */
	public function getHTML(): string {
		$context = RequestContext::getMain();
		if ( !$context->getTitle() || !$context->getTitle()->exists() ) {
			return '';
		}
		$entity = $this->manager->getCheckoutEntity( $context->getTitle() );
		if ( !$entity ) {
			return '';
		}
		$user = $entity->getUser();
		$payload = $entity->getPayload();
		$alertText = $payload['alertText'] ?? null;

		if ( $alertText ) {
			return $alertText;
		}
		$comment = $payload['comment'] ?? null;
		$realname = empty( $user->getRealName() ) ? $user->getName() : $user->getRealName();
		if ( $comment ) {
			return $context->msg( 'pagecheckout-alertbanner-checkout-with-comment' )
				->params( $user->getName(), $realname, $comment )
				->text();
		}
		return $context->msg( 'pagecheckout-alertbanner-checkout' )
			->params( $user->getName(), $realname )
			->text();
	}

	/**
	 * @return string
	 */
	public function getType(): string {
		return IAlertProvider::TYPE_DANGER;
	}

}
