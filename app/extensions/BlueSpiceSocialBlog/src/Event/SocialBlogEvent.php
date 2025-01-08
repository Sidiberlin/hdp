<?php

namespace BlueSpice\Social\Blog\Event;

use BlueSpice\EntityFactory;
use BlueSpice\Social\Blog\Entity\Blog as BlogEntity;
use BlueSpice\Social\Event\SocialEvent;
use MediaWiki\Permissions\GroupPermissionsLookup;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Message;
use MWException;
use MWStake\MediaWiki\Component\Events\Delivery\IChannel;
use MWStake\MediaWiki\Component\Events\EventLink;
use SpecialPage;
use stdClass;
use Title;
use Wikimedia\Rdbms\ILoadBalancer;

class SocialBlogEvent extends SocialEvent {

	/**
	 * @param ILoadBalancer $lb
	 * @param UserFactory $userFactory
	 * @param GroupPermissionsLookup $gpl
	 * @param EntityFactory $entityFactory
	 * @param UserIdentity $agent
	 * @param stdClass $entityData
	 * @param string $action
	 */
	public function __construct(
		ILoadBalancer $lb, UserFactory $userFactory, GroupPermissionsLookup $gpl, EntityFactory $entityFactory,
		UserIdentity $agent, stdClass $entityData, string $action = self::ACTION_EDIT
	) {
		parent::__construct( $lb, $userFactory, $gpl, $entityFactory, $agent, $entityData, $action );
	}

	/**
	 * @return Message
	 */
	public function getKeyMessage(): Message {
		return Message::newFromKey( "bs-social-event-blog-$this->action-desc" );
	}

	/**
	 * @inheritDoc
	 */
	public function getMessage( IChannel $forChannel ): Message {
		return Message::newFromKey( "bs-social-event-blog-$this->action" )->params(
			$this->getAgent()->getName(),
			$this->entity->get( BlogEntity::ATTR_BLOG_TITLE )
		);
	}

	/**
	 * @return Title|null
	 * @throws MWException
	 */
	protected function getWatchedTitle() {
		// For blogs, related title is always Main_page, need to get the actual related page from tags
		$fromTag = $this->getRelatedTitleFromTags( $this->entity );
		if ( $fromTag ) {
			return $fromTag;
		}

		return SpecialPage::getTitleFor( 'Blog' );
	}

	/**
	 * @inheritDoc
	 */
	public function getLinks( IChannel $forChannel ): array {
		$relevant = $this->doGetRelevantTitle();
		if ( !$relevant ) {
			return [];
		}
		return [
			new EventLink(
				$relevant->getFullURL(),
				Message::newFromKey( 'bs-social-notification-blog-primary-link-label' )
			)
		];
	}

	/**
	 * @return string
	 */
	public function getKey(): string {
		return 'bs-social-event-blog';
	}
}
