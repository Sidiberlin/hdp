<?php

namespace MediaWiki\Extension\EnhancedStandardUIs\Action;

use ChangeTags;
use ExtensionRegistry;
use FormatJson;
use HistoryAction;
use Html;
use InvalidArgumentException;
use LogEventsList;
use MediaWiki\Extension\EnhancedStandardUIs\IHistoryPlugin;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use Message;
use RawMessage;

class EnhancedHistoryAction extends HistoryAction {

	/**
	 * @return string
	 */
	public function getName() {
		return 'history';
	}

	/**
	 * Print the history page for an article.
	 * @return string|null
	 */
	public function onView() {
		$out = $this->getOutput();
		$user = $out->getUser();
		$request = $this->getRequest();
		$config = $this->context->getConfig();
		$services = MediaWikiServices::getInstance();

		// Setup page variables.
		$out->setFeedAppendQuery( 'action=history' );

		$this->addHelpLink(
			'https://www.mediawiki.org/wiki/Special:MyLanguage/Help:History',
			true
		);

		// Fail nicely if article doesn't exist.
		if ( !$this->getWikiPage()->exists() ) {
			$send404Code = $config->get( MainConfigNames::Send404Code );
			if ( $send404Code ) {
				$out->setStatusCode( 404 );
			}
			$out->addWikiMsg( 'nohistory' );

			$dbr = $services->getDBLoadBalancer()->getConnection( DB_REPLICA );

			# show deletion/move log if there is an entry
			LogEventsList::showLogExtract(
				$out,
				[ 'delete', 'move', 'protect' ],
				$this->getTitle(),
				'',
				[ 'lim' => 10,
					'conds' => [ 'log_action != ' . $dbr->addQuotes( 'revision' ) ],
					'showIfEmpty' => false,
					'msgKey' => [ 'moveddeleted-notice' ]
				]
			);

			return null;
		}

		$tagFilter = $request->getVal( 'tagfilter' );

		/**
		 * Option to show only revisions that have been (partially) hidden via RevisionDelete
		 */
		if ( $request->getBool( 'deleted' ) ) {
			$conds = [ 'rev_deleted != 0' ];
		} else {
			$conds = [];
		}

		$this->getHookRunner()->onPageHistoryBeforeList(
			$this->getArticle(),
			$this->getContext()
		);

		$res = $this->doDBQuery( $services, $conds, $tagFilter );

		$data = [];
		$userFactory = $services->getUserFactory();
		$permissionManager = $services->getPermissionManager();
		$language = $services->getContentLanguage();
		$titleFactory = $services->getTitleFactory();

		$registry = ExtensionRegistry::getInstance()->getAttribute(
			'EnhancedStandardUIsHistoryPagePlugins'
		);

		$objectFactory = $services->getObjectFactory();
		$historyPagePlugin = [];
		foreach ( $registry as $key => $spec ) {
			$object = $objectFactory->createObject( $spec );
			if ( !( $object instanceof IHistoryPlugin ) ) {
				throw new InvalidArgumentException(
					"Invalid history plugin \"$key\""
				);
			}
			$historyPagePlugin[$key] = $object;
		}

		$hasPermission = $permissionManager->userHasRight( $user, 'deletedtext' );
		$oldSize = 0;
		foreach ( $res as $row ) {
			$classes = [];
			$deletedFields = $this->bitsToDeletedFields( $row->rev_deleted );
			if ( $deletedFields['revision'] ) {
				$classes[] = 'enhanced-history-revision-strikethrough';
				if ( !$hasPermission ) {
					$classes[] = 'enhanced-history-revision-grey';
				}
			}
			if ( $deletedFields['author'] ) {
				$classes[] = 'enhanced-history-author-strikethrough';
			}
			if ( $deletedFields['summary'] ) {
				$classes[] = 'enhanced-history-summary-strikethrough';
			}

			$sizeDiff = $row->rev_len - $oldSize;
			$entry['diff'] = Message::newFromKey( 'size-bytes', $sizeDiff )->parse();
			if ( $sizeDiff < 0 ) {
				$classes[] = 'enhanced-history-diff-minus';
			} elseif ( $sizeDiff > 0 ) {
				$classes[] = 'enhanced-history-diff-plus';
			}

			$entry['check'] = false;
			$entry['id'] = $row->rev_id;
			$entry['revision'] = $language->userTimeAndDate( $row->rev_timestamp, $user );
			$entry['revisionUrl'] = $hasPermission || !$deletedFields['revision']
				? $this->getTitle()->getLocalURL( [ 'oldid' => $row->rev_id ] )
				: '';
			$entry['author'] = $hasPermission || !$deletedFields['author']
				? $userFactory->newFromActorId( $row->rev_actor )->getName()
				: '';
			$entry['size'] = Message::newFromKey( 'size-bytes', $row->rev_len )->parse();
			$summary = new RawMessage( $row->rev_comment_text );
			$entry['summary'] = $hasPermission || !$deletedFields['summary']
				? $summary->parse()
				: '';
			$entry['tags'] = $row->ts_tags;
			$entry['tagUrl'] = $titleFactory->newFromText( 'Special:Tags' )->getLocalURL();

			$attribs = [];
			foreach ( $historyPagePlugin as $plugin ) {
				$plugin->ammendRow( $this, $entry, $attribs, $classes );
			}
			$entry['classes'] = $classes;
			$entry['attribs'] = $attribs;

			$data[] = $entry;
			$oldSize = $row->rev_len;
		}
		$orderedData = array_reverse( $data );

		$modules = [ 'ext.enhancedstandarduis.history' ];
		foreach ( $historyPagePlugin as $plugin ) {
			$pluginModules = $plugin->getRLModules( $this );
			foreach ( $pluginModules as $module ) {
				$modules[] = $module;
			}
		}

		$out->addModules( $modules );
		$out->addBodyClasses( 'enhanced-action-history' );
		return $out->addHTML(
			Html::element( 'div',
			[
				'id' => 'enhanced-history-cnt',
				'data-history' => FormatJson::encode( $orderedData )
			] ) );
	}

	/**
	 * Converts revision.rev_deleted bitfield to an array with keys
	 *
	 * @param int $bits
	 * @return array
	 *   - 'revision' => Revision text
	 *   - 'author' => Editor's username/IP address
	 *   - 'summary' => Edit summary
	 */
	private function bitsToDeletedFields( $bits ): array {
		return [
			'revision' => $this->isDeleted( $bits, RevisionRecord::DELETED_TEXT ),
			'author' => $this->isDeleted( $bits, RevisionRecord::DELETED_USER ),
			'summary' => $this->isDeleted( $bits, RevisionRecord::DELETED_COMMENT ),
		];
	}

	/**
	 * Checks if RevisionRecord::DELETED_* field is set
	 *
	 * @param int $bits revision.rev_deleted bitfield
	 * @param int $field RevisionRecord::DELETED_* field to check
	 * @return bool
	 */
	private function isDeleted( $bits, $field ): bool {
		return ( $bits & $field ) == $field;
	}

	/**
	 *
	 * @param MediaWikiServices $services
	 * @param array $conds
	 * @param string|null $tagFilter
	 * @return IResultWrapper
	 */
	private function doDBQuery( $services, $conds, $tagFilter ) {
		$fname = __METHOD__ . ' (EnhancedHistoryAction)';
		$info = $this->getQueryInfo( $services, $conds, $tagFilter );
		$tables = $info['tables'];
		$fields = $info['fields'];
		$conds = $info['conds'] ?? [];
		$options = $info['options'] ?? [];
		$join_conds = $info['join_conds'] ?? [];
		list( $tables, $fields, $conds, $fname, $options, $join_conds ) =
			[ $tables, $fields, $conds, $fname, $options, $join_conds ];

		$db = $services->getDBLoadBalancer()->getConnection( DB_REPLICA );
		return $db->select( $tables, $fields, $conds, $fname, $options, $join_conds );
	}

	/**
	 *
	 * @param MediaWikiServices $services
	 * @param array $conds
	 * @param string|null $tagFilter
	 * @return array
	 */
	private function getQueryInfo( $services, $conds, $tagFilter ) {
		$revQuery = $services->getRevisionStore()->getQueryInfo( [ 'user' ] );

		$queryInfo = [
			'tables' => $revQuery['tables'],
			'fields' => $revQuery['fields'],
			'conds' => array_merge(
				[ 'rev_page' => $this->getWikiPage()->getId() ],
				$conds ),
			'options' => [ 'USE INDEX' => [ 'revision' => 'rev_page_timestamp' ] ],
			'join_conds' => $revQuery['joins'],
		];
		ChangeTags::modifyDisplayQuery(
			$queryInfo['tables'],
			$queryInfo['fields'],
			$queryInfo['conds'],
			$queryInfo['join_conds'],
			$queryInfo['options'],
			$tagFilter
		);

		$this->getHookRunner()->onPageHistoryPager__getQueryInfo( $this, $queryInfo );

		return $queryInfo;
	}

}
