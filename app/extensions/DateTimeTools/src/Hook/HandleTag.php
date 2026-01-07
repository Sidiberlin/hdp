<?php

namespace MediaWiki\Extension\DateTimeTools\Hook;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\DateTimeTools\DateTimeParser;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Html\Html;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;

class HandleTag implements ParserFirstCallInitHook {
	/** @var DateTimeParser */
	private $dateTimeParser;

	/**
	 * @var array
	 */
	private static $counter = [];

	/**
	 * @param DateTimeParser $parser
	 */
	public function __construct( DateTimeParser $parser ) {
		$this->dateTimeParser = $parser;
	}

	/**
	 * @inheritDoc
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setHook( 'datetime', [ $this, 'handleTag' ] );
	}

	/**
	 * @param string $input
	 * @param array $args
	 * @param Parser $parser
	 * @param PPFrame $frame
	 *
	 * @return string
	 */
	public function handleTag( $input, array $args, Parser $parser, PPFrame $frame ) {
		if ( isset( static::$counter[spl_object_id( $parser )] ) ) {
			static::$counter[spl_object_id( $parser )]++;
		} else {
			static::$counter[spl_object_id( $parser )] = 0;
		}

		$dt = $this->dateTimeParser->parseTimestamp( $input );
		if ( !$dt ) {
			return 'Invalid date format';
		}
		$language = RequestContext::getMain()->getLanguage();
		if ( $this->dateTimeParser->isMidnight( $dt ) ) {
			$formatted = $language->userDate( $dt->format( 'YmdHid' ), $parser->getUserIdentity() );
		} else {
			$formatted = $language->userTimeAndDate( $dt->format( 'YmdHid' ), $parser->getUserIdentity() );
		}

		$parser->getOutput()->addModuleStyles( [ 'ext.dateTimeTools.styles' ] );
		return Html::element( 'span', [
			'class' => 'datetime'
		], $formatted );
	}
}
