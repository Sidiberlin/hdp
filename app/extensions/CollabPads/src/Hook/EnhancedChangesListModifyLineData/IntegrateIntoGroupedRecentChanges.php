<?php

namespace MediaWiki\Extension\CollabPads\Hook\EnhancedChangesListModifyLineData;

use MediaWiki\Extension\CollabPads\CollabRevisionManager;
use MediaWiki\Hook\EnhancedChangesListModifyLineDataHook;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Title\Title;

class IntegrateIntoGroupedRecentChanges implements EnhancedChangesListModifyLineDataHook {

	/** @var LinkRenderer */
	private $linkRenderer;

	/** @var CollabRevisionManager */
	private $collabRevisionManager;

	/**
	 * @param LinkRenderer $linkRenderer
	 * @param CollabRevisionManager $collabRevisionManager
	 */
	public function __construct(
		LinkRenderer $linkRenderer,
		CollabRevisionManager $collabRevisionManager
	) {
		$this->linkRenderer = $linkRenderer;
		$this->collabRevisionManager = $collabRevisionManager;
	}

	/**
	 * @inheritDoc
	 */
	public function onEnhancedChangesListModifyLineData( $changesList, &$data,
		$block, $rc, &$classes, &$attribs
	) {
		$revId = intval( $rc->getAttribute( 'rc_this_oldid' ) );
		$collabpadParticipants = $this->collabRevisionManager->getParticipants( (int)$revId );

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

			$data[] = $spanTag;
			$data[ 'recentChangesFlags' ][ 'collab-edit' ] = true;
		}
	}
}
