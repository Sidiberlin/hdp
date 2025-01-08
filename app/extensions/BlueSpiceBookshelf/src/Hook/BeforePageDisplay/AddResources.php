<?php

namespace BlueSpice\Bookshelf\Hook\BeforePageDisplay;

class AddResources extends \BlueSpice\Hook\BeforePageDisplay {

	protected function doProcess() {
		$this->out->addModules( [
			'ext.bluespice.bookshelf.navigationTab',
			'mwstake.component.commonui.tree-component'
		] );

		$this->out->addModuleStyles( [
			'ext.bluespice.bookshelf.chapter-pager.styles',
			'ext.bluespice.bookshelf.booknav.styles'
		] );
	}

}
