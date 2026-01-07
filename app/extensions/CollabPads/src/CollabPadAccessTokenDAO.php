<?php

namespace MediaWiki\Extension\CollabPads;

use MWCryptRand;
use Wikimedia\Rdbms\ILoadBalancer;

class CollabPadAccessTokenDAO {

	/**
	 * @var int
	 */
	public const TOKEN_LENGTH = 32;

	/**
	 * @var string
	 */
	private $table = 'collabpad_acl_token';

	/**
	 * @var ILoadBalancer
	 */
	private $lb;

	/**
	 * @param ILoadBalancer $lb
	 */
	public function __construct( ILoadBalancer $lb ) {
		$this->lb = $lb;
	}

	/**
	 * Generates and saves to DB CollabPad "access token" connected with specified user and specified title
	 *
	 * @param int $userId
	 * @param string $titlePrefixedDbKey
	 * @return string Generated token
	 */
	public function create( int $userId, string $titlePrefixedDbKey ): string {
		$token = MWCryptRand::generateHex( self::TOKEN_LENGTH );

		$dbw = $this->lb->getConnection( DB_PRIMARY );
		$dbw->insert(
			$this->table,
			[
				'cat_user_id' => $userId,
				'cat_prefixed_page_title' => $titlePrefixedDbKey,
				'cat_token' => $token,
			],
			__METHOD__
		);

		return $token;
	}

	/**
	 * Gets "access token" in case if it already exists in DB
	 *
	 * @param int $userId
	 * @param string $titlePrefixedDbKey
	 * @return string Token already existing in DB, or empty string if there is no token
	 */
	public function get( int $userId, string $titlePrefixedDbKey ): string {
		// Use primary here just to make sure that we'll avoid bugs because of "replica lag"
		$dbw = $this->lb->getConnection( DB_PRIMARY );

		$token = $dbw->selectField(
			$this->table,
			'cat_token',
			[
				'cat_user_id' => $userId,
				'cat_prefixed_page_title' => $titlePrefixedDbKey,
			],
			__METHOD__
		);

		// Return just empty string if no token was found, to match method return type
		if ( $token === false ) {
			$token = '';
		}

		return $token;
	}

	/**
	 * @param string $token
	 * @param string $titlePrefixedDbKey
	 * @return int ID of user, associated with specified token.
	 * 		<tt>0</tt> if there is no user associated with that token.
	 */
	public function recognizeUser( string $token, string $titlePrefixedDbKey ): int {
		// We have to use primary DB here for reading,
		// because this code may be executed in short time after creating the token.
		// Otherwise, we may have troubles because of "replica lag"
		$dbw = $this->lb->getConnection( DB_PRIMARY );

		return (int)$dbw->selectField(
			$this->table,
			'cat_user_id',
			[
				'cat_token' => $token,
				'cat_prefixed_page_title' => $titlePrefixedDbKey,
			],
			__METHOD__
		);
	}

	/**
	 * @param int $userId
	 * @param string $titlePrefixedDbKey
	 */
	public function dropToken( int $userId, string $titlePrefixedDbKey ): void {
		$dbw = $this->lb->getConnection( DB_PRIMARY );
		$dbw->delete(
			$this->table,
			[
				'cat_user_id' => $userId,
				'cat_prefixed_page_title' => $titlePrefixedDbKey,
			],
			__METHOD__
		);
	}
}
