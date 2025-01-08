<?php

namespace MediaWiki\Extension\UnifiedTaskOverview\Rest;

use MediaWiki\Extension\UnifiedTaskOverview\ITaskDescriptor;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Rest\SimpleHandler;
use RequestContext;
use User;

class ListHandler extends SimpleHandler {

	/**
	 *
	 * @var HookContainer
	 */
	private $hookContainer = null;

	/**
	 *
	 * @param HookContainer $hookContainer
	 */
	public function __construct( $hookContainer ) {
		$this->hookContainer = $hookContainer;
	}

	/**
	 *
	 * @return void
	 */
	public function run() {
		/** @var ITaskDescriptor[] */
		$taskDescs = [];

		$this->hookContainer->run(
			'UnifiedTaskOverviewGetTaskDescriptors', [ &$taskDescs, $this->getContextUser() ]
		);
		$responseData = [];
		/** @var ITaskDescriptor $taskDesc */
		foreach ( $taskDescs as $taskDesc ) {
			$responseData[] = [
				'type' => $taskDesc->getType(),
				'header' => $taskDesc->getHeader()->plain(),
				'subheader' => $taskDesc->getSubHeader()->text(),
				'body' => $taskDesc->getBody()->parse(),
				'url' => $taskDesc->getURL(),
				'sortkey' => $taskDesc->getSortKey(),
				'RLmodules' => $taskDesc->getRLModules()
			];
		}

		return $this->getResponseFactory()->createJson( $responseData );
	}

	/**
	 *
	 * @return User
	 */
	private function getContextUser() {
		return RequestContext::getMain()->getUser();
	}
}
