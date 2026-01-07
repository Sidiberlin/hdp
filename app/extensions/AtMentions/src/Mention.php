<?php

namespace AtMentions;

use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

class Mention implements \JsonSerializable {
	/** @var User */
	private $user;
	/** @var RevisionRecord */
	private $revision;
	/** @var User */
	private $actor;

	/**
	 * @param User $user
	 * @param RevisionRecord $revisionRecord
	 * @param User $actor
	 */
	public function __construct( User $user, RevisionRecord $revisionRecord, User $actor ) {
		$this->user = $user;
		$this->revision = $revisionRecord;
		$this->actor = $actor;
	}

	/**
	 * @return User
	 */
	public function getUser(): User {
		return $this->user;
	}

	/**
	 * @return RevisionRecord
	 */
	public function getRevision(): RevisionRecord {
		return $this->revision;
	}

	/**
	 * @return User
	 */
	public function getActor(): User {
		return $this->actor;
	}

	/**
	 * @return Title
	 */
	public function getTitle(): Title {
		return Title::newFromLinkTarget( $this->revision->getPageAsLinkTarget() );
	}

	/**
	 * @return string|null
	 */
	public function getTimestamp(): ?string {
		return $this->revision->getTimestamp();
	}

	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		return [
			'user' => $this->getUser()->getName(),
			'title' => $this->getTitle()->getPrefixedDBkey(),
			'rev' => $this->getRevision()->getId(),
			'timestamp' => $this->getTimestamp(),
			'actor' => $this->getActor()->getName(),
		];
	}
}
