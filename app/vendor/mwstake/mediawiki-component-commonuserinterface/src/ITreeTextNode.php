<?php

namespace MWStake\MediaWiki\Component\CommonUserInterface;

use Message;

interface ITreeTextNode {

	/**
	 * @return Message
	 */
	public function getText(): Message;
}
