<?php

namespace BlueSpice\ReadConfirmation;

use Title;
use User;

interface IMechanism {

	/**
	 * @param Title $title
	 * @param User $user
	 * @param int|null $revId
	 * @return bool
	 */
	public function canConfirm( Title $title, User $user, $revId = null );

	/**
	 * @param Title $title
	 * @param User $user
	 * @param int|null $revId
	 * @return bool
	 */
	public function confirm( Title $title, User $user, $revId = null );

	/**
	 * @param Title $title
	 * @param User $userAgent
	 * @return User[]|bool
	 */
	public function notify( Title $title, User $userAgent );

	/**
	 * @return void
	 */
	public function autoNotify();

	/**
	 * @return void
	 */
	public function wireUpNotificationTrigger();

	/**
	 * Gets all read confirmation data related to specified users.
	 * For each user specified there will be information about all pages which he marked as "read".
	 * For all of these pages will be returned page's the latest revision, and the latest revision,
	 * marked as "read" by user.
	 * Page will not be included in the list if none of its revisions was marked as "read".
	 *
	 * @param array $userIds List of user IDs
	 * @return array Array with such structure:
	 * 	[
	 * 	  <user_id1> =>
	 *		[
	 * 			<page_id1> => [
	 * 				'latest_rev' => <latest_page_revision>,
	 *				'latest_read_rev' => <latest_page_revision_read_by_user>
	 * 			],
	 * 			<page_id2> => [,
	 * 			...
	 * 			],
	 * 			...
	 * 		],
	 * 	  <user_id2> => [
	 * 	  ...
	 *    ],
	 * 	  ...
	 * 	]
	 */
	public function getLatestReadConfirmations( array $userIds = [] ): array;

	/**
	 * @param array $userIds
	 * @param array $pageIds
	 * @return array [ <page_id> => [ <user_id1>, <user_id2>, ...], ... ]
	 */
	public function getCurrentReadConfirmations( array $userIds = [], array $pageIds = [] );

	/**
	 * @param Title $title
	 * @return mixed
	 */
	public function mustRead( Title $title );
}
