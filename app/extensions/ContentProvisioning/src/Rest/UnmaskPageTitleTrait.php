<?php

namespace MediaWiki\Extension\ContentProvisioning\Rest;

trait UnmaskPageTitleTrait {

	/**
	 * @param string $pageTitle
	 * @return string
	 */
	private function unmaskPageTitle( string $pageTitle ): string {
		$pageTitle = str_replace( '|', '/', $pageTitle );
		return $pageTitle;
	}
}
