<?php

namespace BlueSpice\ReadConfirmation\HookHandler;

use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;

class Skin implements SkinTemplateNavigation__UniversalHook {

	/**
	 * // phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
	 * @inheritDoc
	 */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		if ( !isset( $links['pageassignments'] ) ) {
			return;
		}
		if ( !isset( $links['pageassignments']['data'] ) ) {
			$links['pageassignments']['data'] = [];
		}
		$links['pageassignments']['data']['attentionindicator'] = 'readconfirmation';
	}

}
