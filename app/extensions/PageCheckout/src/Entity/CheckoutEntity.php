<?php

namespace MediaWiki\Extension\PageCheckout\Entity;

use JsonSerializable;
use Title;
use User;

class CheckoutEntity implements JsonSerializable {
	/** @var int */
	private $id;
	/** @var Title */
	private $title;
	/** @var User */
	private $user;
	/** @var array */
	private $payload;

	/**
	 * @param int $id
	 * @param Title $title
	 * @param User $user
	 * @param array|null $payload
	 */
	public function __construct( $id, Title $title, User $user, ?array $payload = [] ) {
		$this->id = (int)$id;
		$this->title = $title;
		$this->user = $user;
		$this->payload = $payload;
	}

	/**
	 * @return int
	 */
	public function getId(): int {
		return $this->id;
	}

	/**
	 * @return Title
	 */
	public function getTitle(): Title {
		return $this->title;
	}

	/**
	 * @return User
	 */
	public function getUser(): User {
		return $this->user;
	}

	/**
	 * @return array
	 */
	public function getPayload(): array {
		return $this->payload;
	}

	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'title' => $this->getTitle()->getPrefixedDBkey(),
			'user' => $this->getUser()->getName(),
			'payload' => $this->getPayload()
		];
	}
}
