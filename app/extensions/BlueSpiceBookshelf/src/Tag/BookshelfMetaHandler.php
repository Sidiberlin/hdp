<?php

namespace BlueSpice\Bookshelf\Tag;

use BlueSpice\Tag\Handler;
use FormatJson;
use Html;

class BookshelfMetaHandler extends Handler {

	/**
	 * @return string
	 */
	public function handle() {
		// TODO: Potential security risk: are sAttributes properly preprocessed here?
		$this->parser->getOutput()->setPageProperty(
			'bs-bookshelf-meta',
			FormatJson::encode( $this->processedArgs )
		);
		$this->parser->getOutput()->setPageProperty(
			'bs-universalexport-meta',
			FormatJson::encode( $this->processedArgs )
		);
		$this->parser->getOutput()->setPageProperty( 'bs-tag-universalexport-meta', 1 );

		$attribs = [
			'class' => 'bs-universalexport-meta'
		];

		return Html::element( 'div', array_merge( $this->processedArgs, $attribs ) );
	}
}
