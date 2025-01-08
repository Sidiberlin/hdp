<?php

namespace MediaWiki\Extension\CollabPads;

use BlueSpice\Discovery\ITitleActionPrimaryActionModifier;

class CollabEditActionModifier implements ITitleActionPrimaryActionModifier {

	/**
	 * @param array $ids
	 * @param string $primaryId
	 * @return string
	 */
	public function getActionId( array $ids, string $primaryId ): string {
		if ( isset( $ids['ca-collabpad'] ) ) {
			return 'ca-collabpad';
		}

		return $primaryId;
	}

}
