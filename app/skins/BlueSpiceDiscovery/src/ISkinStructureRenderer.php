<?php

namespace BlueSpice\Discovery;

use IContextSource;

interface ISkinStructureRenderer {

	/**
	 *
	 * @param IContextSource $context
	 * @return string
	 */
	public function getHtml( $context ): string;
}
