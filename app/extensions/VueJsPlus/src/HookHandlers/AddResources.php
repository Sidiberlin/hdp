<?php

namespace MediaWiki\Extension\VueJsPlus\HookHandlers;

class AddResources {

	/**
	 * @param OutputPage $out
	 * @param Skin $skin
	 * @return void This hook must not abort, it must return no value
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$out->addModules( [
			'ext.vuejsplus-data-grid.vue',
			'ext.vuejsplus-data-tree.vue'
		] );
	}
}
