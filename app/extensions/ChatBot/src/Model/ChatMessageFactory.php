<?php

namespace ChatBot\Model;

use Exception;

class ChatMessageFactory {
	/**
	 * @param array $jsonMessages
	 *
	 * @return ChatMessage[]
	 * @throws Exception
	 */
	public function makeMessages( array $jsonMessages ): array {
		return array_map( function ( $message ) {
			return $this->makeMessage( $message );
		}, $jsonMessages );
	}

	/**
	 * @param array $message
	 *
	 * @return ChatMessage
	 * @throws Exception
	 */
	public function makeMessage( array $message ): ChatMessage {
		return ChatMessage::fromMessageJson( $message );
	}
}
