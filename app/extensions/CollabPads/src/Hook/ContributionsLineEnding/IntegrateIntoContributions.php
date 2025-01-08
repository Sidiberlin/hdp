<?php

namespace MediaWiki\Extension\CollabPads\Hook\ContributionsLineEnding;

use ChangesList;
use ContribsPager;
use Html;
use MediaWiki\Extension\CollabPads\CollabRevisionManager;
use MediaWiki\Hook\ContribsPager__getQueryInfoHook;
use MediaWiki\Hook\SpecialContributions__formatRow__flagsHook;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\User\UserFactory;
use Title;

class IntegrateIntoContributions
	implements ContribsPager__getQueryInfoHook, SpecialContributions__formatRow__flagsHook {

	/** @var LinkRenderer */
	private $linkRenderer;

	/** @var CollabRevisionManager */
	private $collabRevisionManager;

	/** @var UserFactory */
	private $userFactory;

	/**
	 * @param LinkRenderer $linkRenderer
	 * @param CollabRevisionManager $collabRevisionManager
	 * @param UserFactory $userFactory
	 */
	public function __construct(
		LinkRenderer $linkRenderer,
		CollabRevisionManager $collabRevisionManager,
		UserFactory $userFactory
	) {
		$this->linkRenderer = $linkRenderer;
		$this->collabRevisionManager = $collabRevisionManager;
		$this->userFactory = $userFactory;
	}

	/**
	 * // phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
	 * @inheritDoc
	 */
	public function onContribsPager__getQueryInfo( $pager, &$queryInfo ) {
		$title = $pager->getRequest()->getValues()['title'];
		$userName = str_replace( "Special:Contributions/", "", $title );
		$user = $this->userFactory->newFromName( $userName );
		if ( !$user ) {
			return false;
		}

		// Add the collabpad_revision table to the query
		$queryInfo['tables'][] = "collabpad_revision";
		$queryInfo['join_conds']["collabpad_revision"] = [ 'LEFT JOIN', "sr_rev_id = rev_id" ];

		// create a new query conditions
		unset( $queryInfo['conds'] );
		$queryInfo['conds'][] =
			"(sr_participants LIKE " . $pager->getDatabase()->addQuotes( '%"' . $userName . '"%' ) .
			") OR (sr_participants IS NULL AND rev_actor = " . $user->getActorId() . ")";

		return true;
	}

	/**
	 * // phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
	 * @inheritDoc
	 */
	public function onSpecialContributions__formatRow__flags( $context, $row, &$flags ) {
		$collapPadSession = $this->collabRevisionManager->getSessionByRevId( $row->rev_id );
		if ( !empty( $collapPadSession ) ) {
			array_unshift( $flags, ChangesList::flag( 'collab-edit' ) );
		}
	}

	/**
	 * @param ContribsPager $pager
	 * @param string &$ret
	 * @param stdClass $row
	 * @param string[] &$classes
	 * @param string[] &$attribs
	 */
	public function onContributionsLineEnding( $pager, &$ret, $row, &$classes, &$attribs
	) {
		$collabpadParticipants = $this->collabRevisionManager->getParticipants( (int)$row->rev_id );

		if ( !empty( $collabpadParticipants ) ) {
			$participantLinks = '';
			foreach ( $collabpadParticipants as $key => $participant ) {
				$participantLinks .= Html::rawElement(
					'span',
					[],
					$this->linkRenderer->makeLink(
						Title::makeTitle( NS_USER, $participant ),
						$participant
					)
				);
			}
			$spanTag = Html::rawElement(
				'span',
				[ 'class' => 'collabpads-participants' ],
				$participantLinks
			);

			$ret .= " " . $spanTag;
			$classes[] = 'collab-edit';
		}
	}
}
