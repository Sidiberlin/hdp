<?php

namespace BlueSpice\Social\Comments\Event;

use BlueSpice\EntityFactory;
use BlueSpice\Social\Blog\Entity\Blog;
use BlueSpice\Social\Entity;
use BlueSpice\Social\Event\SocialTextEvent;
use MediaWiki\Permissions\GroupPermissionsLookup;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Message;
use MWStake\MediaWiki\Component\Events\Delivery\IChannel;
use MWStake\MediaWiki\Component\Events\EventLink;
use stdClass;
use Title;
use User;
use Wikimedia\Rdbms\ILoadBalancer;

class SocialCommentEvent extends SocialTextEvent {

	/** @var SpecialPageFactory */
	protected $specialPageFactory;

	/** @var Entity */
	protected $parentEntity;

	/** @var User */
	protected $parentEntityOwner;

	/**
	 * @param ILoadBalancer $lb
	 * @param UserFactory $userFactory
	 * @param GroupPermissionsLookup $gpl
	 * @param EntityFactory $entityFactory
	 * @param SpecialPageFactory $specialPageFactory
	 * @param UserIdentity $agent
	 * @param stdClass $entityData
	 * @param string $action
	 */
	public function __construct(
		ILoadBalancer $lb, UserFactory $userFactory, GroupPermissionsLookup $gpl, EntityFactory $entityFactory,
		SpecialPageFactory $specialPageFactory, UserIdentity $agent,
		stdClass $entityData, string $action = self::ACTION_EDIT
	) {
		parent::__construct( $lb, $userFactory, $gpl, $entityFactory, $agent, $entityData, $action );
		$this->specialPageFactory = $specialPageFactory;
		$this->getParentEntityInfo();
	}

	/**
	 * @inheritDoc
	 */
	public function getLinks( IChannel $forChannel ): array {
		return [
			new EventLink(
				$this->getLinkTargetTitle()->getFullURL(),
				Message::newFromKey( 'bs-social-notification-comment-primary-link-label' )
			)
		];
	}

	/**
	 * @return Message
	 */
	public function getKeyMessage(): Message {
		return Message::newFromKey( "bs-social-comment-event-$this->action-desc" );
	}

	/**
	 * @inheritDoc
	 */
	public function getMessage( IChannel $forChannel ): Message {
		return Message::newFromKey( "bs-social-comment-event-$this->action" )->params(
			$this->getAgent()->getName(), $this->getTitleAnchor( $this->doGetRelevantTitle(), $forChannel )
		);
	}

	/**
	 *
	 * @return Title
	 */
	protected function getWatchedTitle() {
		$parent = $this->entity->getParent();
		if ( $parent instanceof Blog ) {
			$titleFromTags = $this->getRelatedTitleFromTags( $parent );
			if ( $titleFromTags instanceof Title ) {
				return $titleFromTags;
			}
		}
		return $parent->getRelatedTitle();
	}

	/**
	 *
	 * @return int[]
	 */
	protected function getUserIdsToSkip() {
		$usersToSkip = parent::getUserIdsToSkip();
		if ( $this->parentEntityOwner instanceof User ) {
			$usersToSkip[] = $this->parentEntityOwner->getId();
		}

		return $usersToSkip;
	}

	/**
	 * @inheritDoc
	 */
	public function getLinkTargetTitle(): Title {
		$title = $this->entity->getTitle();
		if ( $title instanceof Title && $title->exists() ) {
			$topicsSP = $this->specialPageFactory->getPage( 'Topics' );
			return $topicsSP->getPageTitle( $this->entity->get( Entity::ATTR_ID ) );
		}

		return parent::getTitle();
	}

	/**
	 *
	 * @return void
	 */
	protected function getParentEntityInfo() {
		if ( !$this->entity->hasParent() ) {
			return;
		}

		$this->parentEntity = $this->entity->getParent();
		$this->parentEntityOwner = $this->parentEntity->getOwner();
	}

	/**
	 * @return string
	 */
	public function getKey(): string {
		return 'bs-social-comment-event';
	}
}
