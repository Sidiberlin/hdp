<?php

namespace AtMentions\Hook;

use AtMentions\Event\UserMentionInEntity;
use AtMentions\MentionParser;
use AtMentions\MentionStore;
use BlueSpice\Entity;
use BlueSpice\Social\Blog\Entity\Blog;
use BlueSpice\Social\Comments\Entity\Comment;
use BlueSpice\Social\Entity\Text;
use BlueSpice\Social\Topics\Entity\Topic;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MWException;
use MWStake\MediaWiki\Component\Events\Notifier;
use NamespaceInfo;
use Status;
use Title;
use TitleFactory;
use WikitextContent;

class ProcessMentionsInEntities extends ProcessMentions {

	/** @var array */
	private $supportedEntities = [
		Comment::class => Comment::ATTR_TEXT,
		Topic::class => Topic::ATTR_TEXT
	];

	/** @var array */
	private $entities = [];

	/** @var NamespaceInfo */
	private $namespaceInfo;

	/**
	 * @param MentionParser $parser
	 * @param MentionStore $mentionStore
	 * @param RevisionStore $revisionStore
	 * @param Notifier $notifier
	 * @param TitleFactory $titleFactory
	 * @param NamespaceInfo $namespaceInfo
	 * @param UserFactory $userFactory
	 */
	public function __construct(
		MentionParser $parser, MentionStore $mentionStore, RevisionStore $revisionStore,
		Notifier $notifier, TitleFactory $titleFactory,
		NamespaceInfo $namespaceInfo, UserFactory $userFactory
	) {
		parent::__construct(
			$parser, $mentionStore, $revisionStore, $notifier, $titleFactory, $userFactory
		);
		$this->namespaceInfo = $namespaceInfo;
	}

	/**
	 * @param Entity $entity
	 * @param Status $status
	 * @param UserIdentity $user
	 *
	 * @return void
	 */
	public function onBSEntitySaveComplete( Entity $entity, Status $status, UserIdentity $user ) {
		if ( !$status->isOK() || !$this->isEntitySupported( $entity ) ) {
			return;
		}
		$this->processEntity( $entity, $user );
	}

	/**
	 * @param Entity $entity
	 * @param Status $status
	 * @param UserIdentity $user
	 *
	 * @return void
	 */
	public function onBSEntityDelete( Entity $entity, Status $status, UserIdentity $user ) {
		if ( !$this->isEntitySupported( $entity ) ) {
			return;
		}
		$title = $this->getEntityTitleObject( $entity );
		$this->store->removeMentionsForPageId( $title->getArticleID() );
	}

	/**
	 * @param Entity $entity
	 * @param Status $status
	 * @param UserIdentity $user
	 *
	 * @return void
	 */
	public function onBSEntityUndeleteComplete( Entity $entity, Status $status, UserIdentity $user ) {
		if ( !$status->isOK() || !$this->isEntitySupported( $entity ) ) {
			return;
		}
		$this->processEntity( $entity, $user, false );
	}

	/**
	 * @param Entity $entity
	 * @param UserIdentity $user
	 * @param bool|null $compareToPrev
	 *
	 * @return void
	 * @throws MWException
	 */
	private function processEntity( Entity $entity, UserIdentity $user, ?bool $compareToPrev = true ) {
		$revision = $this->getRevisionFromEntity( $entity, $user );
		if ( !$revision ) {
			return;
		}
		$this->entities[$entity->get( Entity::ATTR_ID )] = $entity;
		$this->parseOnSave( $revision, $user, $compareToPrev );
	}

	/**
	 * @param Entity $entity
	 *
	 * @return bool
	 */
	private function isEntitySupported( Entity $entity ): bool {
		return isset( $this->supportedEntities[get_class( $entity )] );
	}

	/**
	 * @param Entity $entity
	 *
	 * @return Title
	 */
	private function getEntityTitleObject( Entity $entity ): Title {
		return $this->titleFactory->makeTitle( NS_SOCIALENTITY, $entity->get( Entity::ATTR_ID ) );
	}

	/**
	 * @param Entity $entity
	 * @param UserIdentity $user
	 *
	 * @return RevisionRecord|null
	 * @throws MWException
	 */
	private function getRevisionFromEntity( Entity $entity, UserIdentity $user ): ?RevisionRecord {
		$field = $this->supportedEntities[get_class( $entity )];
		$text = $entity->get( $field );
		if ( !is_string( $text ) ) {
			return null;
		}
		$entityTitle = $this->getEntityTitleObject( $entity );
		$revision = new MutableRevisionRecord( $entityTitle );
		$revision->setContent( 'main', new WikitextContent( $text ) );
		$revision->setId( $entityTitle->getLatestRevID() );
		$revision->setUser( $user );

		return $revision;
	}

	/**
	 * @param UserIdentity $mentionedUser
	 * @param RevisionRecord $revisionRecord
	 * @param UserIdentity $actor
	 *
	 * @return void
	 * @throws \Exception
	 */
	protected function emitEvent( UserIdentity $mentionedUser, RevisionRecord $revisionRecord, UserIdentity $actor ) {
		$title = $revisionRecord->getPageAsLinkTarget();
		if ( $title->getNamespace() !== NS_SOCIALENTITY ) {
			// Should never be called on non-entity pages
			parent::emitEvent( $mentionedUser, $revisionRecord, $actor );
			return;
		}
		$entity = $this->entities[$title->getDBkey()];
		if ( !$entity ) {
			parent::emitEvent( $mentionedUser, $revisionRecord, $actor );
			return;
		}

		$parent = $entity instanceof Comment ? $entity->getParent() : null;
		if ( $parent && $parent instanceof Blog ) {
			$related = \SpecialPage::getTitleFor( 'Blog' );
		} else {
			$related = $this->titleFactory->newFromText( $entity->get( Text::ATTR_RELATED_TITLE ) );
			if ( $related && $related->isTalkPage() ) {
				$related = $this->titleFactory->newFromLinkTarget( $this->namespaceInfo->getSubjectPage( $related ) );
			}
		}

		$event = new UserMentionInEntity(
			$mentionedUser, $actor,
			$this->titleFactory->makeTitle( NS_SOCIALENTITY, $entity->get( Entity::ATTR_ID ) ),
			$entity instanceof Text ? $entity->get( Text::ATTR_TEXT ) : '', $related
		);

		$this->notifier->emit( $event );
	}
}
