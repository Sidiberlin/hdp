<?php

namespace BlueSpice\ReadConfirmation\Hook\UserMergeAccountFields;

use BlueSpice\DistributionConnector\Hook\UserMergeAccountFields;

class MergeReadConfirmationDBFields extends UserMergeAccountFields {
	protected function doProcess() {
		$this->updateFields[] = [ 'bs_readconfirmation', 'rc_user_id' ];
	}
}
