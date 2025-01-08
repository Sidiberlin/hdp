<?php

namespace MediaWiki\Extension\NotifyMe\Rest;

use MediaWiki\Extension\NotifyMe\Storage\NotificationStore;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\Validator\JsonBodyValidator;
use MWStake\MediaWiki\Component\Events\Delivery\NotificationStatus;
use RequestContext;
use User;
use Wikimedia\ParamValidator\ParamValidator;

class ChangeWebNotificationStatus extends SimpleHandler {
	/** @var NotificationStore */
	private $store;

	/**
	 * @param NotificationStore $store
	 */
	public function __construct( NotificationStore $store ) {
		$this->store = $store;
	}

	/**
	 * @return bool
	 */
	public function needsReadAccess() {
		return true;
	}

	/**
	 * @return bool
	 */
	public function needsWriteAccess() {
		return false;
	}

	/**
	 * @return Response
	 * @throws HttpException
	 */
	public function run() {
		$requested = $this->getRequestedIds();
		$setStatusToAll = false;
		if ( $this->shouldSetStatusToAll( $requested ) ) {
			$notifications = $this->store->forUser( $this->getUser() )->query();
			$setStatusToAll = true;
		} else {
			$ids = array_keys( $requested );
			$notifications = $this->store->query( [
				'ni_id IN (' . implode( ',', $ids ) . ')',
			] );
		}

		$updated = [];
		foreach ( $notifications as $notification ) {
			if ( $notification->getTargetUser()->getId() !== $this->getUser()->getId() ) {
				// Cannot update other people's notifications
				$updated[$notification->getId()] = false;
				continue;
			}
			$shouldBeCompleted = $setStatusToAll ? $requested['*'] : $requested[$notification->getId()];
			if ( $shouldBeCompleted ) {
				if ( !$notification->getStatus()->isPending() ) {
					$updated[$notification->getId()] = false;
					continue;
				}
				$notification->getStatus()->markAsCompleted();
			} else {
				if ( !$notification->getStatus()->isCompleted() ) {
					$updated[$notification->getId()] = false;
					continue;
				}
				$notification->getStatus()->setStatus( NotificationStatus::STATUS_PENDING );
			}

			$this->store->persist( $notification );
			$updated[$notification->getId()] = true;
		}

		return $this->getResponseFactory()->createJson( $updated );
	}

	/**
	 * @param array $requested
	 *
	 * @return bool
	 */
	private function shouldSetStatusToAll( array $requested ): bool {
		return isset( $requested['*'] );
	}

	/**
	 * @return User
	 */
	private function getUser() {
		return RequestContext::getMain()->getUser();
	}

	/**
	 * @return array
	 * @throws HttpException
	 */
	protected function getRequestedIds(): array {
		$body = $this->getValidatedBody();
		if ( !isset( $body['notifications'] ) ) {
			throw new HttpException( 'Invalid request. Body must contain `notifications` key', 400 );
		}
		if ( !is_array( $body['notifications'] ) ) {
			throw new HttpException( 'Invalid request. `notifications` must be an array', 400 );
		}
		$notifications = $body['notifications'];
		// Ensure that array has ints as keys and bools as values
		foreach ( $notifications as $id => $completed ) {
			if ( ( !is_int( $id ) && $id !== '*' ) || !is_bool( $completed ) ) {
				throw new HttpException(
					'Invalid request. `notifications` must be in format { id|"*": true|false }', 400
				);
			}
		}
		return $notifications;
	}

	/**
	 * @param string $contentType
	 *
	 * @return JsonBodyValidator
	 * @throws HttpException
	 */
	public function getBodyValidator( $contentType ) {
		if ( $contentType === 'application/json' ) {
			return new JsonBodyValidator( [
				'notifications' => [
					ParamValidator::PARAM_REQUIRED => true,
				]
			] );
		}
		throw new HttpException( 'Invalid content type', 400 );
	}
}
