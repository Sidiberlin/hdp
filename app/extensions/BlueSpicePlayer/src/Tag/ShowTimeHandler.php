<?php

namespace BlueSpice\Player\Tag;

use BlueSpice\Tag\Handler;
use File;
use Title;

class ShowTimeHandler extends Handler {

	/**
	 * @var File
	 */
	private $file = null;

	/**
	 * @var \RepoGroup
	 */
	private $repoGroup = null;

	/**
	 * @var array|string[]
	 */
	private $allowedFileExtensions = [ 'flv', 'mp4', 'ogv', 'ogg', 'webm' ];

	/**
	 * ShowTimeHandler constructor.
	 * @param string $processedInput
	 * @param array $processedArgs
	 * @param \Parser $parser
	 * @param \PPFrame $frame
	 * @param \RepoGroup $repoGroup
	 */
	public function __construct(
		$processedInput,
		array $processedArgs,
		\Parser $parser,
		\PPFrame $frame,
		$repoGroup
	) {
		parent::__construct( $processedInput, $processedArgs, $parser, $frame );
		$this->repoGroup = $repoGroup;
	}

	/**
	 * @return string
	 */
	public function handle() {
		$this->initFileFromInput();
		$this->validateFileExtension();

		$attributes = [];
		$attributes['class'] = 'bs-video';
		$attributes['autoplay'] = $this->processedArgs['autostart'];
		$attributes['style'] = "height:" . $this->processedArgs['height'] . "px;";
		$attributes['loop'] = $this->processedArgs['repeat'];
		$attributes['controls'] = true;

		$html = \Html::openElement( 'div', [
			'style' =>
				"width: "
				. $this->processedArgs['width'] .
				"px;"
		] );

		$html .= \HTML::rawElement( 'video', $attributes,
			\HTML::element( 'source', [
				"src" => $this->file->getViewURL( false ),
				"type" => $this->file->getMimeType()
			] )
		);
		$html .= \Html::closeElement( 'div' );

		return $html;
	}

	/**
	 * Check if file exist
	 */
	private function initFileFromInput() {
		$fileTitle = Title::makeTitle( NS_FILE, $this->processedInput );
		$this->file = $this->repoGroup->findFile( $fileTitle );
		if ( $this->file instanceof File === false ) {
			throw new \MWException( 'bs-player-file-does-not-exist' );
		}
	}

	/**
	 * Validate Allowed file Extensions
	 */
	private function validateFileExtension() {
		$lcFileExt = strtolower( $this->file->getExtension() );
		if ( !in_array( $lcFileExt, $this->allowedFileExtensions ) ) {
			throw new \MWException( 'bs-player-unsupported-type' );
		}
	}
}
