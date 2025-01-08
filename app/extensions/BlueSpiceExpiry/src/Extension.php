<?php

namespace BlueSpice\Expiry;

use MediaWiki\MediaWikiServices;

class Extension extends \BlueSpice\Extension {
	/** @var (\stdClass|false)[] */
	public static $expirys = [
		'queryExpired' => [],
		'queryAll' => []
	];

	/**
	 *
	 * @param int $articleId
	 * @param bool|null $onlyExpired
	 * @return \stdClass|false
	 */
	public static function getExpiryForPage( $articleId, $onlyExpired = true ) {
		// Terrible -.-
		$type = $onlyExpired ? 'queryExpired' : 'queryAll';
		// basic caching, do not ask for an article id twice per call
		if ( isset( self::$expirys[$type][$articleId] ) ) {
			return self::$expirys[$type][$articleId];
		}

		$conds = [ 'exp_page_id' => $articleId ];
		if ( $onlyExpired ) {
			$conds[] = 'exp_date <= CURDATE()';
		}
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()
			->getConnection( DB_REPLICA );
		$res = $dbr->select(
			[ 'bs_expiry' ],
			'*',
			$conds,
			__METHOD__
		);

		if ( $res && $res->numRows() ) {
			$row = $res->fetchRow();
			self::$expirys[$type][$articleId] = $row;
		} else {
			self::$expirys[$type][$articleId] = false;
		}
		return self::$expirys[$type][$articleId];
	}
}
