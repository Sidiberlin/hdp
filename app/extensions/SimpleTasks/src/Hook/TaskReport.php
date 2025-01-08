<?php

namespace SimpleTasks\Hook;

use FormatJson;
use Html;
use MediaWiki\Hook\ParserFirstCallInitHook;
use Parser;
use PPFrame;

class TaskReport implements ParserFirstCallInitHook {

	public const NAME = 'taskreport';

	/**
	 * @var array
	 */
	private static $counter = [];

	/**
	 *
	 */
	public function __construct() {
	}

	/**
	 * @param Parser $parser
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setHook( static::NAME, [ $this, 'onTaskReport' ] );
	}

	/**
	 * @param string|null $input
	 * @param array $args
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string
	 */
	public function onTaskReport( ?string $input, array $args, Parser $parser,
		PPFrame $frame ) {
		if ( isset( static::$counter[spl_object_id( $parser )] ) ) {
			static::$counter[spl_object_id( $parser )]++;
		} else {
			static::$counter[spl_object_id( $parser )] = 0;
		}

		$filter = [];
		if ( isset( $args['user'] ) ) {
			$filter['user'] = $args[ 'user' ];
		}

		if ( isset( $args[ 'date' ] ) ) {
			$filter['date'] = $args[ 'date' ];
		}

		if ( isset( $args[ 'status' ] ) ) {
			$filter['state'] = $args[ 'status' ];
		}

		if ( isset( $args[ 'namespaces' ] ) ) {
			$filter['namespace'] = $args[ 'namespaces' ];
		}
		$parser->getOutput()->addModuleStyles( [ 'ext.simpletasks.taskreport.styles' ] );
		$parser->getOutput()->addModules( [ 'ext.simpletasks.taskreport' ] );
		$count = static::$counter[spl_object_id( $parser )];
		$out = Html::element( 'div', [
			'class' => 'task-report load',
			'data-filter' => FormatJson::encode( $filter ),
			'data-no' => $count,
		] );
		return $out;
	}

}
