<?php

namespace MWStake\MediaWiki\Component\CommonUserInterface;

use Message;

interface IBreadCrumb {

	/**
	 * @return string[]
	 */
	public function getClasses(): array;

	/**
	 * @return Message
	 */
	public function getAriaLabel(): Message;
}
