<?php

namespace MediaWiki\Extension\UnifiedTaskOverview;

use Message;
use RawMessage;

class SimpleTaskDescriptor implements ITaskDescriptor {
	/** @var string */
	private $type;
	/** @var string */
	private $url;
	/** @var Message */
	private $headerMsg;
	/** @var Message */
	private $subheaderMsg;
	/** @var Message */
	private $bodyMsg;
	/** @var int */
	private $sortkey;
	/** @var array */
	private $rlModules;

	/**
	 * @param string $type
	 * @param string $url
	 * @param Message|string $header
	 * @param Message|string|null $subheader
	 * @param Message|string|null $body
	 * @param int|null $sortkey
	 * @param array|null $rlModules
	 */
	public function __construct(
		$type, $url, $header, $subheader = '', $body = '', ?int $sortkey = 100, $rlModules = []
	) {
		$this->type = $type;
		$this->url = $url;
		$this->headerMsg = $header instanceof Message ? $header : new RawMessage( $header );
		$this->subheaderMsg = $subheader instanceof Message ?
			$subheader : new RawMessage( $subheader );
		$this->bodyMsg = $body instanceof Message ? $body : new RawMessage( $body );
		$this->sortkey = $sortkey;
		$this->rlModules = $rlModules;
	}

	/**
	 *
	 * @inheritDoc
	 */
	public function getType(): string {
		return $this->type;
	}

	/**
	 *
	 * @inheritDoc
	 */
	public function getURL(): string {
		return $this->url;
	}

	/**
	 *
	 * @inheritDoc
	 */
	public function getHeader(): Message {
		return $this->headerMsg;
	}

	/**
	 *
	 * @inheritDoc
	 */
	public function getSubHeader(): Message {
		return $this->subheaderMsg;
	}

	/**
	 *
	 * @inheritDoc
	 */
	public function getBody(): Message {
		return $this->bodyMsg;
	}

	/**
	 *
	 * @inheritDoc
	 */
	public function getSortKey(): int {
		return $this->sortkey;
	}

	/**
	 *
	 * @inheritDoc
	 */
	public function getRLModules(): array {
		return $this->rlModules;
	}
}
