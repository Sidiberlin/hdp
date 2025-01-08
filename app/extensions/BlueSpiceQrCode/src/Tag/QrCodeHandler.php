<?php

namespace BlueSpice\QrCode\Tag;

use BlueSpice\DynamicFileDispatcher\Params;
use BlueSpice\DynamicFileDispatcher\UrlBuilder;
use BlueSpice\QrCode\DynamicFileDispatcher\QrCode as DynamicFileDispatcherQrCode;
use BlueSpice\QrCode\Tag\QrCode as TagQrCode;
use BlueSpice\Tag\Handler;
use Html;
use Parser;
use PPFrame;
use RequestContext;
use TitleFactory;

class QrCodeHandler extends Handler {

	/** @var RequestContext */
	private $context = null;

	/** @var TitleFactory */
	private $titleFactory = null;

	/** @var UrlBuilder */
	private $urlBuilder = null;

	/**
	 * @param string $processedInput
	 * @param array $processedArgs
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @param RequestContext $context
	 * @param TitleFactory $titleFactory
	 * @param UrlBuilder $urlBuilder
	 */
	public function __construct( $processedInput, array $processedArgs, Parser $parser,
		PPFrame $frame, RequestContext $context, TitleFactory $titleFactory, $urlBuilder ) {
		parent::__construct( $processedInput, $processedArgs, $parser, $frame );
		$this->context = $context;
		$this->titleFactory = $titleFactory;
		$this->urlBuilder = $urlBuilder;
	}

	/**
	 * @inheritDoc
	 */
	public function handle() {
		$page = $this->processedArgs[TagQrCode::PARAM_PAGE];
		$query = $this->processedArgs[TagQrCode::PARAM_QUERY];
		$desc = $this->processedArgs[TagQrCode::PARAM_DESC];
		$size = $this->processedArgs[TagQrCode::PARAM_SIZE];
		$title = null;

		if ( $page !== '' ) {
			$title = $this->titleFactory->newFromText( $page );
		}
		if ( !$title ) {
			$title = $this->context->getTitle();
		}
		if ( !$title ) {
			return '';
		}

		$altText = $this->context->msg( 'bs-qr-code-alt-text', $title->getText() );

		$url = $this->urlBuilder->build( new Params( [
			Params::MODULE => DynamicFileDispatcherQrCode::MODULE_NAME,
			DynamicFileDispatcherQrCode::PAGENAME => $title->getPrefixedText(),
			DynamicFileDispatcherQrCode::QUERY => $query,
			DynamicFileDispatcherQrCode::SIZE => $size,
		] ) );

		$img = Html::element( 'img', [ 'src' => $url, 'alt' => $altText, 'class' => 'qrCodeImg' ] );
		if ( $desc === '' ) {
			return $img;
		}

		$text = Html::element( 'span', [ 'class' => 'qrCodeText', 'style' => 'display: block;' ], $desc );
		return $text . $img;
	}

}
