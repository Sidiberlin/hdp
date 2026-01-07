<?php

namespace MediaWiki\Extension\CollabPads\Hook\EnhancedChangesListModifyLineData;

use MediaWiki\Extension\CollabPads\CollabRevisionManager;
use MediaWiki\Hook\EnhancedChangesListModifyBlockLineDataHook;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Title\Title;

class IntegrateIntoNonGroupedRecentChanges implements EnhancedChangesListModifyBlockLineDataHook {

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
	public function onEnhancedChangesListModifyBlockLineData( $changesList, &$data, $rc ): void {
		$revId = intval( $rc->getAttribute( 'rc_this_oldid' ) );
		$collabpadParticipants = $this->collabRevisionManager->getParticipants( (int)$revId );
		if ( !empty( $collabpadParticipants ) ) {
			$participantLinks = '';
			// Create the participants section
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
