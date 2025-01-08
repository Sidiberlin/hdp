<?php

namespace ChatBot\DeepsetApi;

use Exception;

class ListApi extends Connector {

	private const LIST_LIMIT = 100;

	/**
	 * @param array $titles
	 *
	 * @return array
	 * @throws Exception
	 */
	public function getFiles( array $titles ): array {
		$filter = $this->getFilter( $titles );
		$queryParams = sprintf( '?limit=%s&filter=%s', self::LIST_LIMIT, $filter );
		$url = $this->indexUrl . $queryParams;
		$files = $this->get( $url );

		return array_map( fn( $file ) => $file['name'], $files['data'] );
	}

	/**
	 * Filter based on prefixed titles
	 *
	 * @param array $titles
	 *
	 * @return array|null
	 */
	private function getFilter( array $titles ): string {
		$wrappedTitles = array_map( fn( $title ) => "'" . $title . "'", $titles );

		if ( count( $wrappedTitles ) === 1 ) {
			return sprintf( 'prefixed_title eq %s', $wrappedTitles[0] );
		}

		return sprintf(
			'prefixed_title in (%s)',
			implode( ",", array_map( fn( $title ) => "'" . $title . "'", $titles ) )
		);
	}
}
