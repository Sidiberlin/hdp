<?php

namespace MWStake\MediaWiki\Component\CommonUserInterface\Component;

use Message;
use MWStake\MediaWiki\Component\CommonUserInterface\IBadge;
use RawMessage;

class Badge extends ComponentBase implements IBadge {

	/**
	 *
	 * @param array $options
	 */
	public function __construct( $options ) {
		$this->options = array_merge(
			[
				'id' => 'simple-badge',
				'classes' => [],
				'text' => new RawMessage( 'SimpleLink' ),
			],
			$options
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return $this->options['id'];
	}

	/**
	 * @inheritDoc
	 */
	public function getClasses(): array {
		return $this->options['classes'];
	}

	/**
	 * @inheritDoc
	 */
	public function getText(): Message {
		return $this->options['text'];
	}
}
