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
	 * @param int $articleId
	 * @param bool|null $onlyExpired
	 * @return \stdClass|false
	 */
	public static function getExpiryForPage( $articleId, $onlyExpired = true ) {
		$type = $onlyExpired ? 'queryExpired' : 'queryAll';
		// basic caching, do not ask for an article id twice per call
		if ( isset( self::$expirys[$type][$articleId] ) ) {
			return self::$expirys[$type][$articleId];
		}

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$conds = [ 'exp_page_id' => $articleId ];
		if ( $onlyExpired ) {
			$conds[] = 'exp_date <= ' . $dbr->addQuotes( $dbr->timestamp( time() ) );
		}

		$res = $dbr->newSelectQueryBuilder()
			->table( 'bs_expiry' )
			->fields( '*' )
			->where( $conds )
			->caller( __METHOD__ )
			->fetchResultSet();

		if ( $res && $res->numRows() ) {
			$row = $res->fetchRow();
			self::$expirys[$type][$articleId] = $row;
		} else {
			self::$expirys[$type][$articleId] = false;
		}
		return self::$expirys[$type][$articleId];
	}
}
