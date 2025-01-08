<?php

namespace BlueSpice\Bookshelf\ExtendedSearch\LookupModifier;

use BS\ExtendedSearch\Source\LookupModifier\LookupModifier;

class AddBookAggregation extends LookupModifier {

	/**
	 * @return void
	 */
	public function apply() {
		$this->lookup->setBucketTermsAggregation( 'books' );
	}

	/**
	 * @return void
	 */
	public function undo() {
		$this->lookup->removeBucketTermsAggregation( 'books' );
	}
}
