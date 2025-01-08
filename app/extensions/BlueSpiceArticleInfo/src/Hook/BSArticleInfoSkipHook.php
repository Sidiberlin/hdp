<?php

namespace BlueSpice\ArticleInfo\Hook;

use Title;

interface BSArticleInfoSkipHook {

	/**
	 * @param Title $title
	 * @param bool &$skip
	 */
	public function onBSArticleInfoSkip( Title $title, bool &$skip ): void;
}
