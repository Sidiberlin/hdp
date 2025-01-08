<?php

namespace MediaWiki\Extension\PageCheckout\Repo;

use MediaWiki\Extension\PageCheckout\Entity\CheckoutEntity;
use MWException;
use Title;
use User;
use Wikimedia\Rdbms\DBError;
use Wikimedia\Rdbms\ILoadBalancer;

class CheckoutRepo {
	/** @var ILoadBalancer */
	private $loadBalancer;

	/**
	 * @param ILoadBalancer $loadBalancer
	 *
	 */
	public function __construct( ILoadBalancer $loadBalancer ) {
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * @param Title $title
	 * @return CheckoutEntity|null
	 */
	public function getForPage( Title $title ): ?CheckoutEntity {
		if ( !$title->exists() ) {
			return null;
		}
		$entities = $this->get( [
			'pcl_page_id' => $title->getArticleID()
		] );

		if ( !empty( $entities ) ) {
			return $entities[0];
		}

		return null;
	}

	/**
	 * @param User $user
	 * @return array
	 */
	public function getForUser( User $user ): array {
		if ( !$user->isRegistered() ) {
			return [];
		}
		return $this->get( [
			'pcl_user_id' => $user->getId()
		] );
	}

	/**
	 * @param CheckoutEntity $entity
	 * @return CheckoutEntity
	 */
	public function save( CheckoutEntity $entity ): CheckoutEntity {
		$db = $this->loadBalancer->getConnection( DB_PRIMARY );

		$data = [
			'pcl_page_id' => $entity->getTitle()->getArticleID(),
			'pcl_user_id' => $entity->getUser()->getId(),
			'pcl_payload' => json_encode( $entity->getPayload() ),
		];

		$res = $db->insert(
			'page_checkout_locks',
			$data,
			__METHOD__
		);
		if ( $res ) {
			$id = $db->insertId();
		}

		if ( !$res ) {
			throw new DBError( $db, 'pagecheckout-error-db-insert' );
		}

		$inserted = $this->get( [ 'pcl_id' => $id ] );
		if ( empty( $inserted ) ) {
			throw new DBError( $db, 'pagecheckout-error-db-retrieve-inserted' );
		}

		return array_shift( $inserted );
	}

	/**
	 * @param CheckoutEntity $entity
	 * @return bool
	 * @throws MWException
	 */
	public function delete( CheckoutEntity $entity ): bool {
		if ( !$entity->getId() ) {
			throw new MWException( 'pagecheckout-error-no-checkout-id' );
		}

		$db = $this->loadBalancer->getConnection( DB_PRIMARY );
		return $db->delete( 'page_checkout_locks', [ 'pcl_id' => $entity->getId() ], __METHOD__ );
	}

	/**
	 * @param array|null $conds
	 * @return array
	 */
	private function get( $conds = [] ) {
		$db = $this->loadBalancer->getConnection( DB_REPLICA );
		$conds = array_merge( $conds, [
			'pcl_page_id = page_id',
			'pcl_user_id = user_id'
		] );

		$res = $db->select(
			[ 'pcl' => 'page_checkout_locks', 'p' => 'page', 'u' => 'user' ],
			[ 'pcl.*', 'p.page_id', 'p.page_title', 'p.page_namespace', 'u.*' ],
			$conds,
			__METHOD__
		);

		$entities = [];
		foreach ( $res as $row ) {
			$title = Title::newFromRow( $row );
			$user = User::newFromRow( $row );
			$entities[] = new CheckoutEntity(
				$row->pcl_id,
				$title,
				$user,
				json_decode( $row->pcl_payload, 1 ) ?? []
			);
		}

		return $entities;
	}
}
