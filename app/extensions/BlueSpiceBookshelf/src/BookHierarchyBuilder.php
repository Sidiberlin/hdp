<?php

namespace BlueSpice\Bookshelf;

class BookHierarchyBuilder {

	/**
	 * @param ChapterDataModel[] $chapters
	 * @return array
	 */
	public function build( array $chapters ): array {
		$hierarchy = [];
		for ( $index = 0; $index < count( $chapters ); $index++ ) {
			if ( $this->getLevel( $chapters[$index] ) === 1 ) {
				$item = [
					'chapter_namespace' => $chapters[$index]->getNamespace(),
					'chapter_title' => $chapters[$index]->getTitle(),
					'chapter_name' => $chapters[$index]->getName(),
					'chapter_number' => $chapters[$index]->getNumber(),
					'chapter_type' => $chapters[$index]->getType(),
				];
				if ( isset( $chapters[$index + 1] ) ) {
					$children = $this->getChildren( $chapters, $index );
					if ( !empty( $children ) ) {
						$item['chapter_children'] = $children;
					}
				}
				$hierarchy[] = $item;
			}
		}

		return $hierarchy;
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
	 * @param int $idx
	 * @return array
	 */
	private function getChildren( array $chapters, int $idx ): array {
		/** @var ChapterDataModel */
		$parent = $chapters[$idx];
		$hierarchy = [];

		for ( $index = $idx + 1; $index < count( $chapters ); $index++ ) {
			if ( $this->isChildOf( $parent, $chapters[$index] ) ) {
				$item = [
					'chapter_namespace' => $chapters[$index]->getNamespace(),
					'chapter_title' => $chapters[$index]->getTitle(),
					'chapter_name' => $chapters[$index]->getName(),
					'chapter_number' => $chapters[$index]->getNumber(),
					'chapter_type' => $chapters[$index]->getType(),
				];

				if ( isset( $chapters[$index + 1] ) ) {
					$children = $this->getChildren( $chapters, $index );
					if ( !empty( $children ) ) {
						$item['chapter_children'] = $children;
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
