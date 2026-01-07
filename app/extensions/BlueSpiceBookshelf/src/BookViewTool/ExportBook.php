<?php

namespace BlueSpice\Bookshelf\BookViewTool;

use BlueSpice\Bookshelf\IBookViewTool;

class ExportBook implements IBookViewTool {

	/**
	 * @return string
	 */
	public function getType(): string {
		return IBookViewTool::TYPE_BUTTON;
	}

	/**
	 * @return string
	 */
	public function getLabelMsgKey(): string {
		return 'bs-bookshelf-book-page-book-action-export-book';
	}

	/**
	 * @return string
	 */
	public function getCallback(): string {
		return 'onBookshelfViewToolExportBook';
	}

	/**
	 * @return array
	 */
	public function getClasses(): array {
		return [ 'bs-view-tool-action-export' ];
	}

	/**
	 * @return string
	 */
	public function getSlot(): string {
		return IBookViewTool::SLOT_RIGHT;
	}

	/**
	 * @return int
	 */
	public function getPosition(): int {
		return 10;
	}

	/**
	 * @return array
	 */
	public function getRLModules(): array {
		return [ 'bs.bookshelf.action.export' ];
	}

	/**
	 * @return string
	 */
	public function getRequiredPermission(): string {
		return 'read';
	}

	/**
	 * @return bool
	 */
	public function requireSelectableTree(): bool {
		return true;
	}
}
