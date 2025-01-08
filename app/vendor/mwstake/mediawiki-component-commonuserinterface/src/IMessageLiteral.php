<?php

namespace MWStake\MediaWiki\Component\CommonUserInterface;

use Message;

interface IMessageLiteral {

	/**
	 * @return Message
	 */
	public function getText(): Message;
}
