<?php

namespace PageHeader\PageInfo;

use IContextSource;
use Message;
use PageHeader\PageInfo;
use RawMessage;

class NULLPageInfo extends PageInfo {
	/**
	 *
	 * @return string
	 */
	public function getItemClass(): string {
		return 'null';
	}

	/**
	 *
	 * @return Message
	 */
	public function getLabelMessage(): Message {
		return new RawMessage( 'Invalid PageInfo' );
	}

	/**
	 *
	 * @return Message
	 */
	public function getTooltipMessage(): Message {
		return new RawMessage( 'Invalid PageInfo' );
	}

	/**
	 *
	 * @param IContextSource $context
	 * @return bool
	 */
	public function shouldShow( $context ): boolean {
		return false;
	}

}
