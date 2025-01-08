<?php

class ExpiryHooks {

	/**
	 *
	 * @param array &$queryPages
	 */
	public static function onQueryPages( &$queryPages ) {
		$queryPages[] = [ 'SpecialExpiry', 'Expired_Articles' ];
	}
}
