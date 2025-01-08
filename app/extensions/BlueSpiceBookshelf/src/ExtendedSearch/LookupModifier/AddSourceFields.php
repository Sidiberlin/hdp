<?php

namespace BlueSpice\Bookshelf\ExtendedSearch\LookupModifier;

use BS\ExtendedSearch\Source\LookupModifier\LookupModifier;

class AddSourceFields extends LookupModifier {

	public function apply() {
		$simpleQS = $this->lookup->getQueryString();
		$fields = [ 'books' ];
		if ( isset( $simpleQS['fields'] ) && is_array( $simpleQS['fields'] ) ) {
			$simpleQS['fields'] = array_merge( $simpleQS['fields'], $fields );
		} else {
			$simpleQS['fields'] = $fields;
		}

		$this->lookup->setQueryString( $simpleQS );
		$this->lookup->addSourceField( 'books' );
	}

	public function undo() {
		$simpleQS = $this->lookup->getQueryString();

		if ( isset( $simpleQS['fields'] ) && is_array( $simpleQS['fields'] ) ) {
			$simpleQS['fields'] = array_diff( $simpleQS['fields'], [ 'books' ] );
		}

		$this->lookup->setQueryString( $simpleQS );
		$this->lookup->removeSourceField( 'books' );
	}
}
