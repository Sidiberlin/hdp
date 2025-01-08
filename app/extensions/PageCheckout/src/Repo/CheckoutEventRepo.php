<?php

namespace MediaWiki\Extension\PageCheckout\Repo;

use DateTime;
use MediaWiki\Extension\PageCheckout\Entity\CheckoutEntity;
use MediaWiki\Extension\PageCheckout\Entity\CheckoutEvent;
use MediaWiki\MediaWikiServices;
use Title;
use User;
use Wikimedia\Rdbms\ILoadBalancer;

class CheckoutEventRepo {
	/** @var ILoadBalancer */
	private $loadBalancer;

	/**
	 * @param ILoadBalancer $loadBalancer
	 */
	public function __construct( ILoadBalancer $loadBalancer ) {
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * @param Title $title
	 * @param int|null $revisionId
	 * @return CheckoutEvent[]
	 */
	public function getForPage( Title $title, $revisionId = null ): array {
		$db = $this->loadBalancer->getConnection( DB_REPLICA );

		$conds = [
			'pce_page_id' => $title->getArticleID(),
			'pce_actor_id = user_id',
			'pce_page_id = page_id',
		];
		if ( $revisionId ) {
			$conds['pce_revision_id'] = $revisionId;
		}
		$res = $db->select(
			[ 'pce' => 'page_checkout_event', 'u' => 'user' ],
			[ 'pce.*', 'u.*' ],

			__METHOD__
		);

		$events = [];
		foreach ( $res as $row ) {
			$user = User::newFromRow( $row );
			$entity = $this->entityFromBlob( $row->pce_lock );

			$events[] = new CheckoutEvent(
				$entity,
				$row->pce_action,
				$user,
				(int)$row->pce_reveision_id,
				DateTime::createFromFormat( 'YmdHis', $row->pce_timestamp ),
				$row->pce_comment ?? ''
			);
		}

		return $events;
	}

	/**
	 * @param CheckoutEvent $event
	 * @return bool
	 */
	public function save( CheckoutEvent $event ): bool {
		$db = $this->loadBalancer->getConnection( DB_PRIMARY );

		return $db->insert(
			'page_checkout_event',
			[
				'pce_action' => $event->getAction(),
				'pce_actor_id' => $event->getActor()->getId(),
				'pce_page_id' => $event->getEntity()->getTitle()->getArticleID(),
				'pce_revision_id' => $event->getRevision(),
				'pce_timestamp' => $db->timestamp( $event->getTime()->format( 'YmdHis' ) ),
				'pce_comment' => $event->getComment(),
				'pce_lock' => json_encode( $event->getEntity() ),
			],
			__METHOD__
		);
	}

	/**
	 * @param string $entityBlob
	 * @return CheckoutEntity|null
	 */
	private function entityFromBlob( $entityBlob ): ?CheckoutEntity {
		$entityData = json_decode( $entityBlob );
		if ( !$entityData ) {
			return null;
		}

		return new CheckoutEntity(
			(int)$entityData['id'],
			Title::newFromText( $entityData['title'] ),
			MediaWikiServices::getInstance()->getUserFactory()->newFromName( $entityData['user'] ),
			$entityData['payload']
		);
	}
}
