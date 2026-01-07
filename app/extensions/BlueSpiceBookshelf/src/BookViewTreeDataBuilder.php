<?php

namespace BlueSpice\Bookshelf;

use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;

class BookViewTreeDataBuilder {

	/** @var TitleFactory */
	private $titleFactory;

	/**
	 * @param TitleFactory $titleFactory
	 */
	public function __construct( TitleFactory $titleFactory ) {
		$this->titleFactory = $titleFactory;
	}

	/**
	 * @param Title $book
	 * @param ChapterDataModel[] $chapters
	 * @param string $bookName
	 * @return array
	 */
	public function build( Title $book, array $chapters, string $bookName = '' ): array {
		if ( $bookName === '' ) {
			$bookName = $book->getText();
		}

		$text = $book->getPrefixedText();
		$query = "book=$text";

		$hierarchy = [];
		for ( $index = 0; $index < count( $chapters ); $index++ ) {
			if ( $this->getLevel( $chapters[$index] ) === 1 ) {
				$labelParts = [
					$chapters[$index]->getNumber(),
					$chapters[$index]->getName()
				];

				if ( $chapters[$index]->getType() === 'plain-text' ) {
					// textnode
					$item = [
						'label' => implode( ' ', $labelParts ),
						'name' => $chapters[$index]->getName(),
						'chapter' => [
							'number' => $chapters[$index]->getNumber(),
							'name' => $chapters[$index]->getName(),
							'type' => $chapters[$index]->getType(),
						]
					];
				} else {
					// link node
					$title = $this->titleFactory->makeTitle(
						$chapters[$index]->getNamespace(),
						$chapters[$index]->getTitle()
					);

					$item = [
						'label' => implode( ' ', $labelParts ),
						'name' => $title->getPrefixedText(),
						'href' => $title->getLocalURL( $query ),
						'chapter' => [
							'namespace' => $chapters[$index]->getNamespace(),
							'title' => $chapters[$index]->getTitle(),
							'number' => $chapters[$index]->getNumber(),
							'name' => $chapters[$index]->getName(),
							'type' => $chapters[$index]->getType(),
						]
					];

					if ( !$title->exists() ) {
						$item['class'] = 'new';
					}
				}

				if ( isset( $chapters[$index + 1] ) ) {
					$children = $this->getChildren( $chapters, $query, $index );
					if ( !empty( $children ) ) {
						$item['children'] = $children;
					}
				}
				$hierarchy[] = $item;
			}
		}

		$bookHierarchy = [
			[
				'label' => $bookName,
				'name' => $book->getText(),
				'chapter' => [
					'number' => '',
					'name' => $book->getText(),
					'type' => 'plain-text',
				],
				'children' => $hierarchy
			]
		];

		return $bookHierarchy;
	}

	/**
	 * @param ChapterDataModel $item
	 * @return int
	 */
	private function getLevel( ChapterDataModel $item ): int {
		$number = $item->getNumber();
		$number = trim( $number, ".\t\n\r\0\x0B" );
		$level = explode( '.', $number );

		return count( $level );
	}

	/**
	 * @param array $chapters
	 * @param string $query
	 * @param int $idx
	 * @return array
	 */
	private function getChildren( array $chapters, string $query, int $idx ): array {
		/** @var ChapterDataModel */
		$parent = $chapters[$idx];
		$hierarchy = [];

		for ( $index = $idx + 1; $index < count( $chapters ); $index++ ) {
			if ( $this->isChildOf( $parent, $chapters[$index] ) ) {
				$labelParts = [
					$chapters[$index]->getNumber(),
					$chapters[$index]->getName()
				];

				if ( $chapters[$index]->getType() === 'plain-text' ) {
					// textnode
					$item = [
						'label' => implode( ' ', $labelParts ),
						'name' => $chapters[$index]->getName(),
						'chapter' => [
							'number' => $chapters[$index]->getNumber(),
							'name' => $chapters[$index]->getName(),
							'type' => $chapters[$index]->getType(),
						]
					];
				} else {
					// link node
					$title = $this->titleFactory->makeTitle(
						$chapters[$index]->getNamespace(),
						$chapters[$index]->getTitle()
					);

					$item = [
						'label' => implode( ' ', $labelParts ),
						'name' => $title->getPrefixedText(),
						'href' => $title->getLocalURL( $query ),
						'chapter' => [
							'namespace' => $chapters[$index]->getNamespace(),
							'title' => $chapters[$index]->getTitle(),
							'number' => $chapters[$index]->getNumber(),
							'name' => $chapters[$index]->getName(),
							'type' => $chapters[$index]->getType(),
						]
					];

					if ( !$title->exists() ) {
						$item['class'] = 'new';
					}
				}

				if ( isset( $chapters[$index + 1] ) ) {
					$children = $this->getChildren( $chapters, $query, $index );
					if ( !empty( $children ) ) {
						$item['children'] = $children;
					}
				}
				$hierarchy[] = $item;
			}
		}
		return $hierarchy;
	}

	/**
	 * @param ChapterDataModel $parent
	 * @param ChapterDataModel $item
	 * @return bool
	 */
	private function isChildOf( ChapterDataModel $parent, ChapterDataModel $item ): bool {
		$parentLevel = $this->getLevel( $parent );
		$itemLevel = $this->getLevel( $item );

		if ( ( $parentLevel + 1 ) !== $itemLevel ) {
			return false;
		}

		$parentChapterNumbers = explode( '.', $parent->getNumber() );
		$childChapterNumbers = explode( '.', $item->getNumber() );

		if ( count( $childChapterNumbers ) <= count( $parentChapterNumbers ) ) {
			return false;
		}

		for ( $i = 0; $i < count( $parentChapterNumbers ); $i++ ) {
			if ( $parentChapterNumbers[$i] !== $childChapterNumbers[$i] ) {
				return false;
			}
		}

		return true;
	}

}
