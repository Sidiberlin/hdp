<?php

namespace MediaWiki\Extension\PageCheckout\Repo;

use MediaWiki\Extension\PageCheckout\Entity\CheckoutEntity;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MWException;
use ObjectCacheFactory;
use Wikimedia\Rdbms\DBError;
use Wikimedia\Rdbms\IConnectionProvider;

class CheckoutRepo {

	/** @var IConnectionProvider */
	private $connectionProvider;

	/** @var ObjectCacheFactory */
	private $objectCacheFactory;

	/**
	 * @param IConnectionProvider $connectionProvider
	 * @param ObjectCacheFactory $objectCacheFactory
	 */
	public function __construct( IConnectionProvider $connectionProvider, ObjectCacheFactory $objectCacheFactory ) {
		$this->connectionProvider = $connectionProvider;
		$this->objectCacheFactory = $objectCacheFactory;
	}

	/**
	 * @param Title $title
	 * @return CheckoutEntity|null
	 */
	public function getForPage( Title $title ): ?CheckoutEntity {
		if ( !$title->exists() ) {
			return null;
		}
		$oc = $this->objectCacheFactory->getLocalServerInstance();
		$cacheKey = $oc->makeKey( 'pagecheckout-page', $title->getArticleID() );
		return $oc->getWithSetCallback(
			$cacheKey,
			$oc::TTL_PROC_SHORT,
			function () use ( $title ) {
				$entities = $this->get( [
					'pcl_page_id' => $title->getArticleID()
				] );

				if ( !empty( $entities ) ) {
					return $entities[0];
				}

				return null;
			}
		);
	}

	/**
	 * @param User $user
	 * @return array
	 */
	public function getForUser( User $user ): array {
		if ( !$user->isRegistered() ) {
			return [];
		}
		$oc = $this->objectCacheFactory->getLocalServerInstance();
		return $oc->getWithSetCallback(
			$oc->makeKey( 'pagecheckout-user-checkouts-', $user->getId() ),
			$oc::TTL_PROC_SHORT,
			function () use ( $user ) {
				return $this->get( [
					'pcl_user_id' => $user->getId()
				] );
			}
		);
	}

	/**
	 * @param CheckoutEntity $entity
	 * @return CheckoutEntity
	 */
	public function save( CheckoutEntity $entity ): CheckoutEntity {
		$dbw = $this->connectionProvider->getPrimaryDatabase();

		$data = [
			'pcl_page_id' => $entity->getTitle()->getArticleID(),
			'pcl_user_id' => $entity->getUser()->getId(),
			'pcl_payload' => json_encode( $entity->getPayload() ),
		];

		$res = $dbw->insert(
			'page_checkout_locks',
			$data,
			__METHOD__
		);
		if ( $res ) {
			$id = $dbw->insertId();
		}

		if ( !$res ) {
			throw new DBError( $dbw, 'pagecheckout-error-db-insert' );
		}

		$inserted = $this->get( [ 'pcl_id' => $id ] );
		if ( empty( $inserted ) ) {
			throw new DBError( $dbw, 'pagecheckout-error-db-retrieve-inserted' );
		}

		$entityToReturn = array_shift( $inserted );
		if ( $entityToReturn instanceof CheckoutEntity ) {
			$this->invalidateCacheForEntity( $entityToReturn );
		}
		return $entityToReturn;
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
		$dbw = $this->connectionProvider->getPrimaryDatabase();
		$res = $dbw->delete( 'page_checkout_locks', [ 'pcl_id' => $entity->getId() ], __METHOD__ );
		$this->invalidateCacheForEntity( $entity );

		return $res;
	}

	/**
	 * @param CheckoutEntity $entity
	 * @return void
	 */
	private function invalidateCacheForEntity( CheckoutEntity $entity ) {
		$oc = $this->objectCacheFactory->getLocalServerInstance();
		$userCC = $oc->makeKey( 'pagecheckout-user-checkouts-', $entity->getUser()->getId() );
		$oc->delete( $userCC );
		$pageCC = $oc->makeKey( 'pagecheckout-page', $entity->getTitle()->getArticleID() );
		$oc->delete( $pageCC );
	}

	/**
	 * @param array|null $conds
	 * @return array
	 */
	private function get( $conds = [] ) {
		$conds = array_merge( $conds, [
			'pcl_page_id = page_id',
			'pcl_user_id = user_id'
		] );

		$dbr = $this->connectionProvider->getReplicaDatabase();

		$res = $dbr->newSelectQueryBuilder()
			->tables( [
				'page_checkout_locks',
				'page',
				'user'
			] )
			->fields( [
				'pcl_id',
				'pcl_payload',
				'page_namespace',
				'page_title',
				'user_id'
			] )
			->where( $conds )
			->caller( __METHOD__ )
			->fetchResultSet();

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
