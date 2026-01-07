<?php

namespace BlueSpice\InterwikiSearch\Hook\BeforePageDisplay;

use BlueSpice\Hook\BeforePageDisplay;
use MediaWiki\Config\ConfigException;
use MediaWiki\Html\Html;
use MediaWiki\SpecialPage\SpecialPage;
use MWException;

class AddModules extends BeforePageDisplay {

	/**
	 * @return bool
	 * @throws ConfigException
	 * @throws MWException
	 */
	protected function skipProcessing() {
		$title = $this->out->getTitle();

		$spTitle = SpecialPage::getTitleFor( 'BSSearchCenter' );
		if ( !$spTitle->equals( $title ) ) {
			return true;
		}

		if ( !$this->getConfig()->get( 'InterwikiSearchSearchInOtherWikis' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * @throws ConfigException
	 * @throws MWException
	 */
	protected function doProcess() {
		$this->out->addModules( 'ext.blueSpiceInterwikiSearch' );

		$this->out->enableOOUI();

		$label = new \OOUI\LabelWidget( [
			'label' => wfMessage( 'bs-interwikisearch-label' )->plain()
		] );
		$itemCnt = Html::element( 'div', [
			'id' => 'bs-interwiki-search-items'
		] );

		$html = Html::openElement( 'div', [
			'aria-describedby' => 'bs-interwiki-search',
			'role' => 'navigation',
			'id' => 'bs-interwiki-search',
			'style' => 'display: none'
		] );

		$html .= $label;
		$html .= $itemCnt;
		$html .= Html::closeElement( 'div' );

		$this->out->addHTML( $html );

		$sources = $this->parseSources();
		$this->out->addJsConfigVars( 'bsgInterwikiSearchSources', $sources );
		$this->out->addJsConfigVars(
			'bsgInterwikiSearchSilentOnError',
			$this->getConfig()->get( 'InterwikiSearchSilentOnError' )
		);
	}

	/**
	 * Validate and normalize source configuration
	 *
	 * @return array
	 * @throws MWException
	 * @throws ConfigException
	 */
	private function parseSources() {
		$raw = $this->getConfig()->get( 'InterwikiSearchSources' );
		$sources = [];
		foreach ( $raw as $key => $config ) {
			$normKey = $this->normalizeKey( $key );
			if ( is_string( $config ) ) {
				$sources[$normKey] = $this->getConfigFromBase( $config, $key );
				continue;
			}
			if ( is_array( $config ) ) {
				if ( isset( $config['base'] ) ) {
					$configFromBase = $this->getConfigFromBase( $config['base'], $key );
					unset( $config['base'] );
					$sources[$normKey] = array_merge( $configFromBase, $config );
					continue;
				}
				if ( $this->verifyConfig( $config, $key ) ) {
					$sources[$normKey] = $config;
				}
			}
		}

		return $sources;
	}

	/**
	 * @param string $key
	 * @return string
	 */
	private function normalizeKey( $key ) {
		return trim( strtolower( str_replace( ' ', '_', $key ) ) );
	}

	/**
	 * @param string $base
	 * @param string $key
	 * @return array
	 * @throws MWException
	 */
	private function getConfigFromBase( $base, $key ) {
		$base = rtrim( $base, '/' );
		return [
			'api-endpoint' => "$base/api.php",
			'search-on-wiki-url' => "$base/index.php?title=Special:SearchCenter&q=$1",
			'name' => $key
		];
	}

	/**
	 * @param array $config
	 * @param string $key
	 * @return bool
	 * @throws MWException
	 */
	private function verifyConfig( array $config, $key ) {
		if (
			isset( $config['api-endpoint' ] ) &&
			isset( $config['search-on-wiki-url'] ) &&
			isset( $config['name'] )
		) {
			return true;
		}

		throw new MWException(
			"BlueSpiceInterwikiSearch: Configuration for target $key is invalid"
		);
	}

}
