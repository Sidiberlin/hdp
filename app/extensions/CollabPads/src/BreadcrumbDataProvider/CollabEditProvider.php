<?php

namespace MediaWiki\Extension\CollabPads\BreadcrumbDataProvider;

use BlueSpice\Discovery\BreadcrumbDataProvider\BaseBreadcrumbDataProvider;
use MediaWiki\Context\RequestContext;
use MediaWiki\Title\Title;

class CollabEditProvider extends BaseBreadcrumbDataProvider {

	/**
	 * @param Title $title
	 * @return array
	 */
	public function getLabels( $title ): array {
		return [
			'text' => $this->messageLocalizer->msg( 'collabpads-content-action-text' )
		];
	}

	/**
	 * @param Title $title
	 * @return bool
	 */
	public function applies( Title $title ): bool {
		$action = RequestContext::getMain()->getRequest()->getText( 'veaction', '' );
		if ( $action === 'collab-edit' ) {
			return true;
		}

		return false;
	}
}
