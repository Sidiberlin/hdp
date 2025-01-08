<?php

namespace BlueSpice\Bookshelf\Data\BooksOverview;

class Record extends \MWStake\MediaWiki\Component\DataStore\Record {
	public const BOOK_NAMESPACE = 'book_namespace';
	public const BOOK_TITLE = 'book_title';
	public const DISPLAYTITLE = 'displaytitle';
	public const SUBTITLE = 'subtitle';
	public const BOOKSHELF = 'bookshelf';
	public const IMAGE_URL = 'image_url';
	public const CHAPTER_NAMESPACE = 'chapter_namespace';
	public const CHAPTER_TITLE = 'chapter_title';
	public const FIRST_CHAPTER_URL = 'first_chapter_url';
	public const ACTIONS = 'actions';
	public const ACTIONS_MODULES = 'modules';
	public const BOOK_EDIT_URL = 'book_edit_url';
}
