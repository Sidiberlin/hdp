<?php

use MediaWiki\Content\WikitextContent;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use Wikimedia\Rdbms\IResultWrapper;

require_once dirname( __DIR__, 3 ) . '/maintenance/Maintenance.php';

class ForceUpdateMentions extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Correct user mention index' );
		$this->addOption( 'namespace', 'Namespace to fix', false, true );
	}

	public function execute() {
		$ns = $this->getOption( 'namespace' );
		$pages = $this->getPages( $ns );

		/** @var \AtMentions\MentionParser $parser */
		$parser = MediaWikiServices::getInstance()->getService( 'AtMentionsParser' );
		$author = User::newSystemUser( 'MediaWiki default', [ 'steal' => true ] );

		if ( !$author ) {
			$this->fatalError( 'Failed to create system user' );
		}
		$hasFixes = false;
		foreach ( $pages as $page ) {
			$title = MediaWikiServices::getInstance()->getTitleFactory()->newFromRow( $page );
			$rev = MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionByTitle( $title );
			if ( !$rev ) {
				continue;
			}

			$content = $rev->getContent( SlotRecord::MAIN );
			if ( !( $content instanceof WikitextContent ) ) {
				continue;
			}
			$mentions = $parser->parse( $content->getText() );
			$mentions = array_map( static function ( $mention ) {
				return $mention['user'] instanceof User ? $mention['user']->getId() : null;
			}, $mentions );
			$mentions = array_values( array_unique( array_filter( $mentions ) ) );
			if ( empty( $mentions ) ) {
				continue;
			}
			$existing = $this->getRawMentionsForPage( $title );
			$missing = array_values( array_diff( $mentions, $existing ) );
			if ( empty( $missing ) ) {
				continue;
			}
			foreach ( $missing as $missingUser ) {
				$this->output( "Adding mention for user $missingUser on page {$title->getFullText()}\n" );
				$this->getDB( DB_PRIMARY )->insert(
					'user_mentions',
					[
						'um_user' => $missingUser,
						'um_author' => $author->getId(),
						'um_page' => $title->getArticleID(),
						'um_rev' => $rev->getId(),
					],
					__METHOD__,
					[ 'IGNORE' ]
				);
				$hasFixes = true;
			}
		}

		if ( !$hasFixes ) {
			$this->output( "No fixes needed\n" );
		}
	}

	/**
	 * @param int|null $ns
	 * @return IResultWrapper
	 */
	private function getPages( ?int $ns ): IResultWrapper {
		$dbr = $this->getDB( DB_REPLICA );

		if ( $ns ) {
			$conds = [ 'page_namespace' => $ns ];
		} else {
			$contentNamespace = MediaWikiServices::getInstance()->getNamespaceInfo()->getContentNamespaces();
			$conds = [ 'page_namespace IN (' . $dbr->makeList( $contentNamespace ) . ')' ];
		}
		return $dbr->select(
			'page',
			[ 'page_id', 'page_title', 'page_namespace' ],
			$conds,
			__METHOD__
		);
	}

	/**
	 * @param Title $title
	 * @return array
	 */
	private function getRawMentionsForPage( Title $title ): array {
		$db = $this->getDB( DB_REPLICA );
		$res = $db->select(
			'user_mentions',
			[ 'um_user' ],
			[ 'um_page' => $title->getArticleID() ],
			__METHOD__
		);

		$mentions = [];
		foreach ( $res as $mention ) {
			$mentions[] = (int)$mention->um_user;
		}

		return array_values( array_unique( $mentions ) );
	}

}

$maintClass = ForceUpdateMentions::class;
require_once RUN_MAINTENANCE_IF_MAIN;
