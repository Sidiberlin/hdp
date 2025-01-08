<?php

namespace MediaWiki\Extension\ImportOfficeFiles\MediaWiki\Hook\MWStakeRunJobsTriggerRegisterHandlers;

use MediaWiki\Extension\ImportOfficeFiles\MediaWiki\RunJobsTriggerHandler\RemoveOrphanedDirectories;

class AddOrphanedDirectoriesHandler {

	/**
	 * @param array &$handlers
	 * @return bool
	 */
	public static function callback( &$handlers ) {
		$handlers[RemoveOrphanedDirectories::HANDLER_KEY] = [
			'class' => RemoveOrphanedDirectories::class,
			'services' => [
				'MainConfig'
			]
		];

		return true;
	}

}
