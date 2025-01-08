<?php

namespace ChatBot\DeepsetApi;

use Exception;

class FeedbackApi extends Connector {

	/**
	 * @param string $feedbackId
	 * @param string $after
	 *
	 * @return array
	 * @throws Exception
	 */
	public function request( string $feedbackId, string $after ) {
		$feedback = json_decode( $after, true );
		$tags = $feedback['tags'];
		if ( count( $tags ) > 0 ) {
			$createdTags = [];
			$existingTagsData = $this->get( $this->tagUrl );
			$existingTags = $existingTagsData['data'];
			foreach ( $tags as $tagName ) {
				$tag = [];
				foreach ( $existingTags as $element ) {
					if ( $tagName === $element['name'] ) {
						$tag = $element;
						break;
					}
				}

				if ( empty( $tag ) ) {
					$tag = $this->getTagId( $tagName );
				}
				if ( isset( $tag['tag_id' ] ) ) {
					$createdTags[] = $tag['tag_id'];
				}
			}
			$feedback['tags'] = $createdTags;

		}
		$options = [
			'body' => json_encode( $feedback )
		];

		try {
			if ( strlen( $feedbackId ) > 0 ) {
				return $this->patch( $this->feedbackUrl . '/' . $feedbackId, $options );
			} else {
				return $this->post( $this->feedbackUrl, $options );
			}
		} catch ( Exception $e ) {
			return [
				'errors' => [ $e->getMessage() ],
			];
		}
	}

	/**
	 * @return array
	 * @throws Exception
	 */
	public function getStats(): array {
		$totalQueries = '-';
		try {
			$pipelineStats = $this->get( $this->pipelineStatsUrl );
			$totalQueries = $pipelineStats['total_queries'] ?? '-';
		} catch ( Exception $e ) {
			// ignore
		}
		$feedbackStats = $this->get( $this->feedbackUrl . '/stats' );
		$feedbackStats['total_queries'] = $totalQueries;
		return $feedbackStats;
	}

	/**
	 *
	 * @param string $tag
	 * @return array|array[]|null
	 */
	private function getTagId( $tag ) {
		$data['name'] = $tag;
		$option = [
			'body' => json_encode( $data )
		];
		try {
			return $this->post( $this->tagUrl, $option );
		} catch ( Exception $e ) {
			return [
				'errors' => [ $e->getMessage() ],
			];
		}
	}

}
