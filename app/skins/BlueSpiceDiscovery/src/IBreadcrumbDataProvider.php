<?php

namespace BlueSpice\Discovery;

use Title;

interface IBreadcrumbDataProvider {

	/**
	 * @param Title $title
	 * @return Title
	 */
	public function getRelevantTitle( $title ): Title;

	/**
	 * @param Title $title
	 * @return array
	 */
	public function getNodes( Title $title ): array;

	/**
	 * @param Title $title
	 * @return array
	 */
	public function getLabels( Title $title ): array;

	/**
	 *
	 * @param Title $title
	 * @return bool
	 */
	public function applies( Title $title ): bool;
}
