<?php

namespace MediaWiki\Extension\CollabPads\Hook\PageHistoryLineEnding;

use MediaWiki\Extension\CollabPads\CollabRevisionManager;
use MediaWiki\Hook\PageHistoryLineEndingHook;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Title\Title;

class IntegrateIntoHistory implements PageHistoryLineEndingHook {

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
	public function onPageHistoryLineEnding( $history, &$row, &$html, &$classes, &$attribs ) {
		$collabpadParticipants = $this->collabRevisionManager->getParticipants( (int)$row->rev_id );

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

			$html .= " " . $spanTag;
		}
	}
}
