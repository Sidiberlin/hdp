<?php

namespace MediaWiki\Extension\TableTools\HookHandler;

class AddResources {

	 /**
	  * @param OutputPage $out
	  * @param Skin $skin
	  * @return void This hook must not abort, it must return no value
	  */
	public function onBeforePageDisplay( $out, $skin ): void {
		$out->addModules( [
			'ext.tabletools.dataTable'
		] );
	}
}
