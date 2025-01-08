<?php

namespace BlueSpice\ReadConfirmation\HookHandler;

use BlueSpice\PageAssignments\Hook\BSPageAssignmentsOverviewHook;

class AddReadConfirmationInfoToPageAssignments implements BSPageAssignmentsOverviewHook {

	/**
	 * @inheritDoc
	 */
	public function onBSPageAssignmentsOverview( array &$deps ): void {
		$deps[] = 'ext.readconfirmation.pageassignmentsintegration';
	}
}
