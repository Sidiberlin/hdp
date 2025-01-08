<?php

namespace BlueSpice\UEModuleHTML;

use BlueSpice\UniversalExport\IExportDialogPlugin;
use IContextSource;

class ExportDialogPluginHTML implements IExportDialogPlugin {

	/**
	 * @return void
	 */
	public static function factory() {
		return new static();
	}

	/**
	 *
	 * @return array
	 */
	public function getRLModules(): array {
		return [ "ext.bluespice.ueModuleHtml.ue-export-dialog-plugin.html" ];
	}

	/**
	 *
	 * @return array
	 */
	public function getJsConfigVars(): array {
		return [];
	}

	/**
	 *
	 * @param IContextSource $context
	 * @return bool
	 */
	public function skip( IContextSource $context ): bool {
		return false;
	}
}
