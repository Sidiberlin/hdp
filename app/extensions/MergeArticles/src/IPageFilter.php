<?php

namespace MergeArticles;

use MediaWiki\Title\Title;

interface IPageFilter {
	/**
	 * @return string
	 */
	public function getId();

	/**
	 * @return string
	 */
	public function getDisplayName();

	/**
	 * Get data to add to the page data, to be filtered on,
	 * since all filtering is done client-side
	 *
	 * @param Title $title
	 * @return mixed
	 */
	public function getFilterableData( Title $title );

	/**
	 * @return string
	 */
	public function getRLModule();

	/**
	 * @return string
	 */
	public function getWidgetClass();

	/**
	 * Custom data to be passed to the widget constructor
	 * @return array
	 */
	public function getWidgetData();

	/**
	 * @return int
	 */
	public function getPriority();
}
