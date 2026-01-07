<?php

namespace BlueSpice\Bookshelf\HookHandler;

use MediaWiki\Hook\PageMoveCompleteHook;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use Wikimedia\Rdbms\LoadBalancer;

class ChapterMove implements PageMoveCompleteHook {

	/** @var TitleFactory */
	private $titleFactory = null;

	/** @var LoadBalancer */
	private $loadBalancer = null;

	/**
	 * @param TitleFactory $titleFactory
	 * @param LoadBalancer $loadBalancer
	 */
	public function __construct( TitleFactory $titleFactory, LoadBalancer $loadBalancer ) {
		$this->titleFactory = $titleFactory;
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * @inheritDoc
	 */
	public function onPageMoveComplete(
		$old, $new, $userIdentity, $pageid, $redirid, $reason, $revision
	) {
		$oldPageName = $this->titleFactory->newFromLinkTarget( $old );
		$chapters = $this->getChapters( $oldPageName );
		if ( empty( $chapters ) ) {
			return;
		}
		$newPageName = $this->titleFactory->newFromLinkTarget( $new );
		$db = $this->loadBalancer->getConnection( DB_PRIMARY );

		foreach ( $chapters as $chapter ) {
			$label = $chapter->chapter_name;
			if ( $oldPageName->getText() === $label ) {
				$label = $newPageName->getText();
			}
			$db->update(
				'bs_book_chapters',
				[
					'chapter_namespace' => $newPageName->getNamespace(),
					'chapter_title' => $newPageName->getDBKey(),
					'chapter_name' => $label
				],
				[ 'chapter_id' => $chapter->chapter_id ],
				__METHOD__
			);
		}
	}

	/**
	 *
	 * @param Title $pageName
	 * @return array
	 */
	private function getChapters( $pageName ) {
		$db = $this->loadBalancer->getConnection( DB_REPLICA );
		$res = $db->select(
			'bs_book_chapters',
			'*',
			[
				'chapter_namespace' => $pageName->getNamespace(),
				'chapter_title' => $pageName->getDBKey()
			],
			__METHOD__
		);

		$chapters = [];
		foreach ( $res as $result ) {
			$chapters[] = $result;
		}

		return $chapters;
	}
}
