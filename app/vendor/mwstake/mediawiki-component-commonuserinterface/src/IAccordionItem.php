<?php

namespace MWStake\MediaWiki\Component\CommonUserInterface;

use Message;

interface IAccordionItem extends IComponent {

	/**
	 *
	 * @return Message
	 */
	public function getHeaderText(): Message;

	/**
	 * @return Message
	 */
	public function getTooltip(): Message;
}
