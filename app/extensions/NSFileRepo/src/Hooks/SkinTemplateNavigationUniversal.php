<?php

namespace NSFileRepo\Hooks;

use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\NSFileRepo\NamespaceList;
use MediaWiki\Json\FormatJson;
use MediaWiki\Message\Message;
use MediaWiki\Title\Title;
use SkinTemplate;

class SkinTemplateNavigationUniversal {

	/**
	 *
	 * @var Config
	 */
	protected $config = null;

	/**
	 *
	 * @var IContextSource
	 */
	protected $context = null;

	/**
	 *
	 * @var SkinTemplate
	 */
	protected $sktemplate = null;

	/**
	 *
	 * @var array
	 */
	protected $links = [];

	/**
	 *
	 * @param SkinTemplate $sktemplate
	 * @param array &$links
	 * @return bool
	 */
	public static function handle( SkinTemplate $sktemplate, &$links ) {
		$instance = new self(
			RequestContext::getMain(),
			new \MediaWiki\Extension\NSFileRepo\Config(),
			$sktemplate,
			$links
		);

		return $instance->process();
	}

	/**
	 *
	 * @param IContextSource $context
	 * @param Config $config
	 * @param SkinTemplate $sktemplate
	 * @param array &$links
	 */
	public function __construct( IContextSource $context, Config $config, SkinTemplate $sktemplate, &$links ) {
		$this->context = $context;
		$this->config = $config;
		$this->sktemplate = $sktemplate;
		$this->links =& $links;
	}

	/**
	 *
	 * @return bool
	 */
	public function process() {
		if ( !$this->isFilePage() ) {
			return true;
		}

		$editableNamespaces = $this->getEditableNamespaces();
		if ( empty( $editableNamespaces ) ) {
			return true;
		}

		$excludeNS = $this->makeExcludeNS( $editableNamespaces );
		$filetitle = Title::newFromText( $this->sktemplate->getTitle()->getDBkey() );

		$this->links['actions']['move-file-namespace'] = [
			'class' => 'nsfr-move-file-namespace',
			'text' => Message::newFromKey( 'nsfilerepo-move-file-namespace-action-label' )->plain(),
			'href' => '#'
		];

		$this->sktemplate->getOutput()->addModules( 'ext.nsfilerepo.filepage.bootstrap' );
		$this->sktemplate->getOutput()->addJsConfigVars( 'wgNSFRMoveFileNamespace', [
			'currentNamespace' => $filetitle->getNamespace(),
			'unprefixedFilename' => $filetitle->getDBkey(),
			'excludeNS' => FormatJson::encode( $excludeNS )
		] );

		return true;
	}

	/**
	 * @return bool
	 */
	private function isFilePage(): bool {
		$title = $this->sktemplate->getTitle();
		return $title && $title->getNamespace() === NS_FILE;
	}

	/**
	 * @param array $editableNamespaces
	 * @return array
	 */
	private function makeExcludeNS( array $editableNamespaces ): array {
		$allNamespaces = array_keys( $this->sktemplate->getLanguage()->getNamespaces() );

		$nonEditableNamespaces = array_diff( $allNamespaces, $editableNamespaces );
		return array_values( $nonEditableNamespaces );
	}

	/**
	 * @return int[]|string[]
	 */
	private function getEditableNamespaces(): array {
		$nsList = new NamespaceList(
			$this->sktemplate->getUser(),
			$this->config,
			$this->sktemplate->getLanguage()
		);

		return array_keys( $nsList->getEditable() );
	}
}
