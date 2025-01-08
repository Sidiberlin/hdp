<?php

namespace AtMentions;

use DateInterval;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Title;
use Wikimedia\Rdbms\ILoadBalancer;

class MentionStore {

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var UserFactory */
	private $userFactory;

	/** @var RevisionStore */
	private $revisionStore;

	/** @var array */
	protected $conds = [];

	/**
	 * @param ILoadBalancer $loadBalancer
	 * @param UserFactory $userFactory
	 * @param RevisionStore $revisionStore
	 */
	public function __construct(
		ILoadBalancer $loadBalancer, UserFactory $userFactory, RevisionStore $revisionStore
	) {
		$this->loadBalancer = $loadBalancer;
		$this->userFactory = $userFactory;
		$this->revisionStore = $revisionStore;
	}

	/**
	 * @param UserIdentity $user
	 * @param Title $title
	 *
	 * @return bool
	 */
	public function removeMention( UserIdentity $user, Title $title ): bool {
		$db = $this->loadBalancer->getConnection( DB_PRIMARY );
		return $db->delete(
			'user_mentions',
			[
				'um_user' => $user->getId(),
				'um_page' => $title->getArticleID()
			],
			__METHOD__
		);
	}

	/**
	 * @param int $pageId
	 *
	 * @return bool
	 */
	public function removeMentionsForPageId( int $pageId ): bool {
		$db = $this->loadBalancer->getConnection( DB_PRIMARY );
		return $db->delete(
			'user_mentions',
			[
				'um_page' => $pageId
			],
			__METHOD__
		);
	}

	/**
	 * @param UserIdentity $user
	 * @param RevisionRecord $revisionRecord
	 * @param UserIdentity $actor
	 *
	 * @return bool
	 */
	public function addMention( UserIdentity $user, RevisionRecord $revisionRecord, UserIdentity $actor ): bool {
		$db = $this->loadBalancer->getConnection( DB_PRIMARY );
		return $db->insert(
			'user_mentions',
			[
				'um_user' => $user->getId(),
				'um_page' => $revisionRecord->getPageId(),
				'um_rev' => $revisionRecord->getId(),
				'um_author' => $actor->getId(),
			],
			__METHOD__
		);
	}

	/**
	 * @param UserIdentity $user
	 * @param RevisionRecord $revisionRecord
	 * @param UserIdentity $actor
	 *
	 * @return bool
	 */
	public function updateMention( UserIdentity $user, RevisionRecord $revisionRecord, UserIdentity $actor ): bool {
		$db = $this->loadBalancer->getConnection( DB_PRIMARY );
		return $db->update(
			'user_mentions',
			[
				'um_rev' => $revisionRecord->getId(),
				'um_author' => $actor->getId(),
			],
			[
				'um_user' => $user->getId(),
				'um_page' => $revisionRecord->getPageId()
			],
			__METHOD__
		);
	}

	/**
	 * @param RevisionRecord $revisionRecord
	 *
	 * @return bool
	 */
	public function moveMentions( RevisionRecord $revisionRecord ): bool {
		$db = $this->loadBalancer->getConnection( DB_PRIMARY );
		return $db->update(
			'user_mentions',
			[
				'um_rev' => $revisionRecord->getId(),
			],
			[
				'um_page' => $revisionRecord->getPageAsLinkTarget()->getArticleID()
			],
			__METHOD__
		);
	}

	/**
	 * @param Title $title
	 *
	 * @return $this
	 */
	public function forPage( Title $title ): MentionStore {
		$this->conds['um_page'] = $title->getArticleID();
		return $this;
	}

	/**
	 * @param UserIdentity $user
	 *
	 * @return $this
	 */
	public function forUser( UserIdentity $user ): MentionStore {
		$this->conds['um_user'] = $user->getId();
		return $this;
	}

	/**
	 * @param UserIdentity $actor
	 *
	 * @return $this
	 */
	public function forActor( UserIdentity $actor ): MentionStore {
		$this->conds['um_author'] = $actor->getId();
		return $this;
	}

	/**
	 * @param DateInterval $dateInterval
	 *
	 * @return $this
	 */
	public function since( DateInterval $dateInterval ): MentionStore {
		$db = $this->loadBalancer->getConnection( DB_PRIMARY );
		$now = new \DateTime();
		$now->sub( $dateInterval );
		$this->conds[] = 'rev_timestamp > ' . $db->addQuotes( $db->timestamp( $now->format( 'YmdHis' ) ) );
		return $this;
	}

	/**
	 * @param array|null $conds
	 *
	 * @return Mention[]
	 */
	public function query( $conds = [] ): array {
		$db = $this->loadBalancer->getConnection( DB_PRIMARY );
		$res = $db->select(
			[ 'user_mentions', 'revision' ],
			[
				'um_user',
				'um_page',
				'um_rev',
				'um_author',
				'rev_timestamp',
			],
			array_merge( $this->conds, $conds ),
			__METHOD__,
			[],
			[
				'revision' => [
					'INNER JOIN',
					[
						'rev_id = um_rev',
					]
				]
			]
		);
		$this->conds = [];

		$mentions = [];
		foreach ( $res as $row ) {
			$mention = $this->mentionFromRow( $row );
			if ( $mention instanceof Mention ) {
				$mentions[] = $mention;
			}
		}
		return $mentions;
	}

	/**
	 * @param \stdClass $row
	 *
	 * @return Mention|null
	 */
	private function mentionFromRow( \stdClass $row ): ?Mention {
		$user = $this->userFactory->newFromId( $row->um_user );
		$actor = $this->userFactory->newFromId( $row->um_author );
		$revision = $this->revisionStore->getRevisionById( $row->um_rev );
		if ( $revision instanceof RevisionRecord ) {
			return new Mention( $user, $revision, $actor );
		}
		return null;
	}
}
