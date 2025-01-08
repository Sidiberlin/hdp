<?php

namespace MediaWiki\Extension\PageCheckout\Entity;

use DateTime;
use JsonSerializable;
use User;

class CheckoutEvent implements JsonSerializable {
	/** @var CheckoutEntity */
	private $entity;
	/** @var string */
	private $action;
	/** @var User */
	private $actor;
	/** @var int */
	private $revision;
	/** @var DateTime */
	private $time;
	/** @var string */
	private $comment;

	/**
	 * @param CheckoutEntity $entity
	 * @param string $action
	 * @param User $actor
	 * @param int $revisionId
	 * @param DateTime $time
	 * @param string $comment
	 */
	public function __construct(
		CheckoutEntity $entity, $action, User $actor,
		int $revisionId, DateTime $time, $comment = ''
	) {
		$this->entity = $entity;
		$this->action = $action;
		$this->actor = $actor;
		$this->revision = $revisionId;
		$this->time = $time;
		$this->comment = (string)$comment;
	}

	/**
	 * @return CheckoutEntity
	 */
	public function getEntity(): CheckoutEntity {
		return $this->entity;
	}

	/**
	 * @return string
	 */
	public function getAction() {
		return $this->action;
	}

	/**
	 * @return User
	 */
	public function getActor(): User {
		return $this->actor;
	}

	/**
	 * @return int
	 */
	public function getRevision(): int {
		return $this->revision;
	}

	/**
	 * @return DateTime
	 */
	public function getTime(): DateTime {
		return $this->time;
	}

	/**
	 * @return string
	 */
	public function getComment() {
		return $this->comment;
	}

	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		return [
			'action' => $this->getAction(),
			'actor' => $this->getActor()->getName(),
			'revision' => $this->getRevision(),
			'time' => $this->getTime()->format( 'YmdHis' ),
			'comment' => $this->comment
		];
	}
}
