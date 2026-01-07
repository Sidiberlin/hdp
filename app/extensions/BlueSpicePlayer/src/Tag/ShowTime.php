<?php

namespace BlueSpice\Player\Tag;

use BlueSpice\ParamProcessor\ParamDefinition;
use BlueSpice\Tag\IHandler;
use BlueSpice\Tag\Tag;
use MediaWiki\Config\Config;
use MediaWiki\Config\ConfigException;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;

class ShowTime extends Tag {

	/**
	 * @var Config
	 */
	private $config = null;

	/**
	 * @param null $config
	 */
	public function __construct( $config = null ) {
		$this->config = $config;
		if ( $this->config === null ) {
			$this->config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'bsg' );
		}
	}

	/**
	 * @return string[]
	 */
	public function getTagNames() {
		return [ 'showtime', 'bs:showtime' ];
	}

	/**
	 * @param mixed $processedInput
	 * @param array $processedArgs
	 * @param Parser $parser
	 * @param PPFrame $frame
	 *
	 * @return IHandler
	 */
	public function getHandler( $processedInput, array $processedArgs, Parser $parser,
		PPFrame $frame ) {
		$repoGroup = MediaWikiServices::getInstance()->getRepoGroup();
		return new ShowTimeHandler( $processedInput, $processedArgs, $parser, $frame, $repoGroup );
	}

	/**
	 * @return array|\BlueSpice\ParamProcessor\IParamDefinition[]
	 * @throws ConfigException
	 */
	public function getArgsDefinitions() {
		$defaultWidth = $this->config->get( 'ShowtimePrefWidth' );
		$defaultHeight = $this->config->get( 'ShowtimePrefHeight' );
		return [
			new ParamDefinition( 'integer', 'width', $defaultWidth ),
			new ParamDefinition( 'integer', 'height', $defaultHeight ),
			new ParamDefinition( 'boolean', 'autostart', false ),
			new ParamDefinition( 'boolean', 'repeat', false )
		];
	}

	/**
	 * Load resources
	 * @return array|string[]
	 */
	public function getResourceLoaderModules() {
		return [
			'ext.bluespice.showtime'
		];
	}

}
