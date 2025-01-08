<?php

namespace MWStake\MediaWiki\Component\CommonUserInterface;

use Message;

interface IAccordion extends IComponent {
	/**
	 *
	 * @return Message
	 */
	public function getLabel(): Message;
}
