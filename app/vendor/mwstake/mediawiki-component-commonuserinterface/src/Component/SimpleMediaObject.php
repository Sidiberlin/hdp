<?php

namespace MWStake\MediaWiki\Component\CommonUserInterface\Component;

use Message;
use MWStake\MediaWiki\Component\CommonUserInterface\IMediaObject;
use RawMessage;

class SimpleMediaObject extends ComponentBase implements IMediaObject {

	/**
	 *
	 * @param string $options
	 */
	public function __construct( $options ) {
		$this->options = array_merge(
			[
				'id' => '',
				'container-classes' => [],
				'image-classes' => [],
				'body-classes' => [],
				'src' => '',
				'alt' => new RawMessage( 'SimpleImageObject' )
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
	public function getContainerClasses(): array {
		return $this->options['container-classes'];
	}

	/**
	 * @inheritDoc
	 */
	public function getImageClasses(): array {
		return $this->options['image-classes'];
	}

	/**
	 * @inheritDoc
	 */
	public function getBodyClasses(): array {
		return $this->options['body-classes'];
	}

	/**
	 * @inheritDoc
	 */
	public function getImageSrc(): string {
		return $this->options['src'];
	}

	/**
	 * @inheritDoc
	 */
	public function getImageAltText(): Message {
		return $this->options['alt'];
	}
}
