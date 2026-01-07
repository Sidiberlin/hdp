<?php

namespace BlueSpice\ProDistributionConnector\Maintenance;

use Exception;
use MediaWiki\Content\FallbackContent;
use MediaWiki\Content\JsonContent;
use MediaWiki\Extension\CommentStreams\Comment;
use MediaWiki\Extension\CommentStreams\Reply;
use MediaWiki\Extension\CommentStreams\Store\TalkPageStore;
use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\User\User;

require_once dirname( __DIR__, 4 ) . '/maintenance/Maintenance.php';

class MigrateSocialCommentsToCommentStreams extends LoggedUpdateMaintenance {

	protected function doDBUpdates() {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'CommentStreams' ) ) {
			$this->error( 'CommentStreams extension is not installed. Skipping migration.' );
			return false;
		}

		$commentStore = $this->getServiceContainer()->getService( 'CommentStreamsStore' );
		if ( !( $commentStore instanceof TalkPageStore ) ) {
			$this->error( 'CommentStreamsStore is not an instance of TalkPageStore. Skipping migration.' );
			return false;
		}
		if ( !$this->hasSocialPages() ) {
			$this->output( "No BlueSpiceSocial content found. Skipping migration.\n" );
			return true;
		}
		$comments = $this->getCommentPages();
		$success = $failed = 0;
		foreach ( $comments as $comment ) {
			try {
				$this->insertComment( $comment, $commentStore );
				$success++;
				$success += count( $comment['children'] );
			} catch ( Exception $ex ) {
				$this->error( $ex->getMessage() );
				$failed++;
				$failed += count( $comment['children'] );
			}
		}
		$this->output( "Successfully migrated $success comments. Failed to migrate $failed comments.\n" );
		return true;
	}

	/**
	 * @return bool
	 */
	private function hasSocialPages(): bool {
		return $this->getDB( DB_REPLICA )->newSelectQueryBuilder()
			->select( [ 'page_id' ] )
			->from( 'page' )
			->where( [ 'page_namespace' => 1506 ] )
			->caller( __METHOD__ )
			->fetchRowCount() > 0;
	}

	/**
	 * @return array
	 */
	private function getCommentPages(): array {
		$pages = $this->getDB( DB_REPLICA )->newSelectQueryBuilder()
			->select( [ 'page_title', 'page_id', 'page_namespace', 'page_touched' ] )
			->from( 'page' )
			->where( [ 'page_namespace' => 1506 ] )
			->caller( __METHOD__ )
			->fetchResultSet();
		$comments = [];
		$replies = [];
		foreach ( $pages as $page ) {
			$title = $this->getServiceContainer()->getTitleFactory()->newFromRow( $page );
			$revision = $this->getServiceContainer()->getRevisionStore()->getRevisionByTitle( $title );
			if ( !$revision ) {
				continue;
			}
			$firstRevision = $this->getServiceContainer()->getRevisionStore()->getFirstRevision( $title );
			/** @var FallbackContent $content */
			$content = $revision->getContent( SlotRecord::MAIN );
			if ( !( $content instanceof JsonContent ) && !( $content instanceof FallbackContent ) ) {
				continue;
			}
			$text = $content->getData();
			$json = json_decode( $text, true );
			if ( !$json ) {
				continue;
			}

			if ( !isset( $json['type'] ) || ( $json['type'] !== 'comment' && $json['type'] !== 'topic' ) ) {
				continue;
			}
			if ( $json['archived'] ) {
				continue;
			}
			$owner = $this->getServiceContainer()->getUserFactory()->newFromId( $json['ownerid'] );
			if ( !$owner->isRegistered() ) {
				$owner = User::newSystemUser( 'MediaWiki default', [ 'steal' => true ] );
			}
			if ( $json['type'] === 'topic' ) {
				$relatedTalk = $this->getServiceContainer()->getTitleFactory()->newFromID( $json['discussiontitleid'] );
				if ( !$relatedTalk ) {
					$this->error( 'No page to attribute comment to:' . $json['topictitle'] );
					continue;
				}
				$subject = $this->getServiceContainer()->getNamespaceInfo()->getSubjectPage( $relatedTalk );
				$comments[$json['id']] = [
					'associated' => $subject,
					'text' => $json['text'],
					'title' => $json['topictitle'],
					'owner' => $owner,
					'created' => $firstRevision->getTimestamp(),
					'modified' => $revision->getTimestamp(),
					'children' => [],
				];
				continue;
			}
			if ( !isset( $replies[$json['parentid']] ) ) {
				$replies[$json['parentid']] = [];
			}
			$replies[$json['parentid']][] = [
				'text' => $json['text'],
				'created' => $firstRevision->getTimestamp(),
				'modified' => $revision->getTimestamp(),
				'owner' => $owner,
			];
		}

		foreach ( $replies as $parentId => $reply ) {
			if ( !isset( $comments[$parentId] ) ) {
				continue;
			}
			$comments[$parentId]['children'] = $reply;
		}

		return $comments;
	}

	/**
	 * @return string
	 */
	protected function getUpdateKey() {
		return 'MigrateSocialCommentsToCommentStreams';
	}

	/**
	 * @param array $comment
	 * @param TalkPageStore $commentStore
	 * @return void
	 */
	private function insertComment( array $comment, TalkPageStore $commentStore ) {
		$inserted = $this->doInsertComment( $comment, $commentStore );
		if ( !$inserted ) {
			$this->error( 'Failed to insert comment' );
			return;
		}
		$this->output( "Inserted comment {$inserted->getTitle()}\n" );
		$commentStore->forceSetEntityData(
			$inserted,
			[
				'created' => $comment['created'],
				'modified' => $comment['modified'],
			],
			User::newSystemUser( 'MediaWiki default', [ 'steal' => true ] )
		);

		if ( isset( $comment['children'] ) ) {
			foreach ( $comment['children'] as $child ) {
				$reply = $this->doInsertReply( $inserted, $child, $commentStore );
				if ( !( $reply instanceof Reply ) ) {
					$this->error( 'Failed to insert reply' );
					continue;
				}
				$this->output( "Inserted reply {$reply->getId()}\n" );
				$commentStore->forceSetEntityData(
					$reply,
					[
						'created' => $child['created'],
						'modified' => $child['modified'],
					],
					User::newSystemUser( 'MediaWiki default', [ 'steal' => true ] )
				);
			}
		}
	}

	/**
	 * @param array $comment
	 * @param TalkPageStore $commentStore
	 * @return Comment|null
	 */
	private function doInsertComment( array $comment, TalkPageStore $commentStore ): ?Comment {
		return $commentStore->insertComment(
			$comment['owner'],
			$comment['text'],
			$this->getServiceContainer()->getTitleFactory()->castFromLinkTarget( $comment['associated'] )->getId(),
			$comment['title'],
			null
		);
	}

	/**
	 * @param Comment $comment
	 * @param array $reply
	 * @param TalkPageStore $commentStore
	 * @return Reply|null
	 */
	private function doInsertReply( Comment $comment, array $reply, TalkPageStore $commentStore ): ?Reply {
		return $commentStore->insertReply(
			$reply['owner'],
			$reply['text'],
			$comment
		);
	}

}

$maintClass = MigrateSocialCommentsToCommentStreams::class;
require_once RUN_MAINTENANCE_IF_MAIN;
