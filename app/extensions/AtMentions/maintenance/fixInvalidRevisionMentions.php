<?php

use MediaWiki\Maintenance\Maintenance;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use Wikimedia\Rdbms\IResultWrapper;

require_once dirname( __DIR__, 3 ) . '/maintenance/Maintenance.php';

class FixInvalidRevisionMentions extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Fix mentions tied to non-existent revisions' );
		$this->addOption( 'dry', 'Do not make any changes' );
	}

	public function execute() {
		$broken = $this->getBrokenMentions();
		if ( $broken->numRows() === 0 ) {
			$this->output( "No broken mentions found\n" );
			return;
		}
		foreach ( $broken as $mentionRow ) {
			$user = MediaWikiServices::getInstance()->getUserFactory()->newFromId( $mentionRow->um_user );
			$title = MediaWikiServices::getInstance()->getTitleFactory()->newFromID( $mentionRow->um_page );
			if ( !$title ) {
				$this->error( "Found mention for title that does not exist, ArticleID: {$mentionRow->um_page}" );
				continue;
			}
			$this->output(
				"Mention for user {$user->getName()} and page {$title->getPrefixedText()}" .
				" is bound to non-existent revision {$mentionRow->um_rev}\n"
			);
			if ( !$this->hasOption( 'dry' ) ) {
				$this->fixMention( $mentionRow, $title );
			}
		}
	}

	/**
	 * @return \Wikimedia\Rdbms\IResultWrapper
	 */
	private function getBrokenMentions(): IResultWrapper {
		$db = $this->getDB( DB_REPLICA );
		return $db->select(
			[ 'um' => 'user_mentions', 'r' => 'revision' ],
			[ 'um_page', 'um_rev', 'um_user' ],
			[ 'rev_id IS NULL' ],
			__METHOD__,
			[],
			[ 'r' => [ 'LEFT JOIN', [ 'um.um_rev = r.rev_id' ] ] ]
		);
	}

	/**
	 * @param stdClass $mentionRow
	 * @param Title $title
	 * @return void
	 */
	private function fixMention( stdClass $mentionRow, Title $title ) {
		$dbw = $this->getDB( DB_PRIMARY );
		$duplicate = $dbw->selectRow(
			[ 'um' => 'user_mentions', 'r' => 'revision' ],
			[ 'um_page', 'um_rev', 'um_user' ],
			[
				'um_user' => $mentionRow->um_user,
				'um_page' => $mentionRow->um_page,
				'rev_id IS NOT NULL'
			],
			__METHOD__,
			[],
			[ 'r' => [ 'LEFT JOIN', [ 'um.um_rev = r.rev_id' ] ] ]
		);
		if ( $duplicate ) {
			// Has another entry which is valid
			$this->output( "Mention already has a valid revision: {$duplicate->um_rev}, deleting wrong entry\n" );
			$dbw->delete(
				'user_mentions',
				[
					'um_page' => $mentionRow->um_page,
					'um_rev' => $mentionRow->um_rev,
					'um_user' => $mentionRow->um_user,
				],
				__METHOD__
			);
			return;
		}
		$latest = $title->getLatestRevID();
		if ( !$latest ) {
			$this->error( "Could not find latest revision for {$title->getPrefixedText()}" );
			return;
		}
		if ( $latest === (int)$mentionRow->um_rev ) {
			$this->error( "Latest revision is already mentioned" );
			return;
		}
		// Update the mention, catching duplicate key error
		try {
			$dbw->update(
				'user_mentions',
				[
					'um_rev' => $latest,
				],
				[
					'um_page' => $mentionRow->um_page,
					'um_rev' => $mentionRow->um_rev,
					'um_user' => $mentionRow->um_user,
				],
				__METHOD__
			);
			$this->output( "Mention updated to revision {$latest}\n" );
		} catch ( Exception $e ) {
			$this->error( "Failed to update mention: {$e->getMessage()}" );
			return;
		}
	}
}

$maintClass = FixInvalidRevisionMentions::class;
require_once RUN_MAINTENANCE_IF_MAIN;
