<?php

namespace SimpleTasks;

use DateTime;
use MediaWiki\Extension\Checklists\ChecklistItem;
use MediaWiki\User\UserIdentity;

class SimpleTask implements \JsonSerializable {

	/** @var ChecklistItem */
	private $check;

	/** @var string */
	private $text;

	/** @var UserIdentity */
	private $user;

	/** @var DateTime|null */
	private $dueDate;

	/**
	 * @param ChecklistItem $check
	 * @param string $text
	 * @param UserIdentity $user
	 * @param DateTime|null $dueDate
	 */
	public function __construct( ChecklistItem $check, string $text, UserIdentity $user, ?DateTime $dueDate ) {
		$this->check = $check;
		$this->text = $text;
		$this->user = $user;
		$this->dueDate = $dueDate;
	}

	/**
	 * @return ChecklistItem
	 */
	public function getChecklistItem(): ChecklistItem {
		return $this->check;
	}

	/**
	 * @return string
	 */
	public function getText(): string {
		return trim( $this->text );
	}

	/**
	 * @return UserIdentity
	 */
	public function getUser(): UserIdentity {
		return $this->user;
	}

	/**
	 * @return DateTime|null
	 */
	public function getDueDate(): ?DateTime {
		return $this->dueDate;
	}

	/**
	 * @return bool
	 */
	public function isCompleted(): bool {
		// TODO: Check-multi-value: Maybe determine from wider range of values?
		// This should always return bool, if one of the multi-statuses is wanted,
		// get it over the ChecklistItem
		return $this->check->getValue() === '1';
	}

	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->check->getId(),
			'text' => $this->getText(),
			'assignee' => $this->user->getName(),
			'dueDate' => $this->dueDate ? $this->dueDate->format( 'YmdHis' ) : null,
			'completed' => $this->isCompleted(),
		];
	}
}
