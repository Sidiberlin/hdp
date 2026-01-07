<?php

namespace MediaWiki\Extension\NumberHeadings\Hook;

use MediaWiki\Title\Title;

interface NumberHeadingsBeforeApply {

	/**
	 * @param bool &$skip
	 * @param string &$prefix
	 * @param Title $title
	 * @param string $html
	 */
	public function onNumberHeadingsBeforeApply( bool &$skip, string &$prefix, Title $title, string $html );
}
