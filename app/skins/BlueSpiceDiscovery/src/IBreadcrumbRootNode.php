<?php

namespace BlueSpice\Discovery;

use Title;

interface IBreadcrumbRootNode {

	/**
	 *
	 * @return Title $title
	 */
	public function getLinkTarget(): Title;

}
