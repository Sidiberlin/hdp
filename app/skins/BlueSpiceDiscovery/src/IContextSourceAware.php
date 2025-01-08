<?php

namespace BlueSpice\Discovery;

use IContextSource;

interface IContextSourceAware {

	/**
	 * @param IContextSource $context
	 * @return void
	 */
	public function setContextSource( IContextSource $context ): void;
}
