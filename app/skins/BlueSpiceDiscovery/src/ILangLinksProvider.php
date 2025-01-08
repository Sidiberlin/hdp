<?php

namespace BlueSpice\Discovery;

use Title;

interface ILangLinksProvider {

	/**
	 * @param string[] $wikitextLangLinks
	 * @param Title $title
	 * @return string[]
	 */
	public function getLangLinks( array $wikitextLangLinks, Title $title ): array;
}
