<?php

namespace BlueSpice\ReadConfirmation\Privacy;

use BlueSpice\Privacy\IPrivacyHandler;
use BlueSpice\Privacy\Module\Transparency;
use MediaWiki\Context\RequestContext;
use MediaWiki\Language\Language;
use MediaWiki\MediaWikiServices;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use Wikimedia\Rdbms\IDatabase;

class Handler implements IPrivacyHandler {
	/** @var IDatabase */
	protected $db;
	/** @var Language */
	protected $language;

	/**
	 *
	 * @param IDatabase $db
	 */
	public function __construct( IDatabase $db ) {
		$this->db = $db;
		$this->language = RequestContext::getMain()->getLanguage();
	}

	/**
	 *
	 * @param string $oldUsername
	 * @param string $newUsername
	 * @return Status
	 */
	public function anonymize( $oldUsername, $newUsername ) {
		return Status::newGood();
	}

	/**
	 *
	 * @param User $userToDelete
	 * @param User $deletedUser
	 * @return Status
	 */
	public function delete( User $userToDelete, User $deletedUser ) {
		$this->db->update(
			'bs_readconfirmation',
			[ 'rc_user_id' => $deletedUser->getId() ],
			[ 'rc_user_id' => $userToDelete->getId() ],
			__METHOD__
		);

		return Status::newGood();
	}

	/**
	 *
	 * @param array $types
	 * @param string $format
	 * @param User $user
	 * @return Status
	 */
	public function exportData( array $types, $format, User $user ) {
		if ( !in_array( Transparency::DATA_TYPE_WORKING, $types ) ) {
			return Status::newGood( [] );
		}
		$res = $this->db->select(
			'bs_readconfirmation',
			'*',
			[ 'rc_user_id' => $user->getId() ],
			__METHOD__
		);

		$data = [];
		foreach ( $res as $row ) {
			$lookup = MediaWikiServices::getInstance()->getRevisionLookup();
			$rev = $lookup->getRevisionById( $row->rc_rev_id );
			if ( !$rev ) {
				continue;
			}
			$title = Title::newFromID( $rev->getPageId() );
			if ( !$title ) {
				continue;
			}

			$timestamp = $this->language->userTimeAndDate(
				$row->rc_timestamp,
				$user
			);

			$data[] = wfMessage(
				'bs-readconfirmation-privacy-transparency-working-rc',
				$title->getPrefixedText(),
				$rev->getId(),
				$timestamp
			)->text();
		}

		return Status::newGood( [
			Transparency::DATA_TYPE_WORKING => $data
		] );
	}
}
