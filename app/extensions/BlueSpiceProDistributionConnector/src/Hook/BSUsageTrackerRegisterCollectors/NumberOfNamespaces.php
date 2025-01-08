<?php

namespace BlueSpice\ProDistributionConnector\Hook\BSUsageTrackerRegisterCollectors;

use BlueSpice\ProDistributionConnector\Collector\NoOfNamespaces;
use BS\UsageTracker\Hook\BSUsageTrackerRegisterCollectors;

class NumberOfNamespaces extends BSUsageTrackerRegisterCollectors {

	protected function doProcess() {
		$this->collectorConfig['bs:namespaces'] = [
			'class' => NoOfNamespaces::class,
			'config' => [ 'identifier' => 'no-of-namespaces' ]
		];
	}

}
