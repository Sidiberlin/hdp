<?php

namespace MediaWiki\Extension\NSFileRepo\HookHandler;

use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\NSFileRepo\Config;
use MediaWiki\Extension\NSFileRepo\NamespaceList;
use MediaWiki\Hook\UploadForm_BeforeProcessingHook;
use MediaWiki\Hook\UploadFormInitDescriptorHook;
use MediaWiki\Hook\UploadVerifyFileHook;
use MediaWiki\HTMLForm\Field\HTMLSelectField;
use MediaWiki\Title\TitleFactory;

class HandleUpload implements
	UploadForm_BeforeProcessingHook,
	UploadVerifyFileHook,
	UploadFormInitDescriptorHook
{

	/**
	 * @var TitleFactory
	 */
	private $titleFactory;

	/**
	 * @var Config
	 */
	private $nsfrConfig;

	/**
	 * @var IContextSource
	 */
	protected $context = null;

	/**
	 * @param TitleFactory $titleFactory
	 */
	public function __construct( TitleFactory $titleFactory ) {
		$this->titleFactory = $titleFactory;
		$this->nsfrConfig = new Config();
		$this->context = RequestContext::getMain();
	}

	/**
	 * @inheritDoc
	 */
	public function onUploadForm_BeforeProcessing( $upload ) {
		$title = $this->titleFactory->newFromText( $upload->mDesiredDestName );
		if ( $title === null ) {
			return true;
		}
		if ( $title->getNamespace() < $this->nsfrConfig->get( Config::CONFIG_THRESHOLD ) ) {
			$upload->mDesiredDestName = preg_replace( "/:/", '-', $upload->mDesiredDestName );
		} else {
			$bits = explode( ':', $upload->mDesiredDestName );
			$ns = array_shift( $bits );
			$upload->mDesiredDestName = $ns . ":" . implode( "-", $bits );
		}
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function onUploadVerifyFile( $upload, $mime, &$error ) {
		$destName = $upload->getDesiredDestName();
		if ( !$destName ) {
			return;
		}
		$title = $this->titleFactory->newFromText( $destName );
		// There is a colon in the name, but it was not a valid namespace prefix!
		if ( !$title || str_contains( $title->getText(), ':' ) ) {
			$error = 'illegal-filename';
			return false;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onUploadFormInitDescriptor( &$descriptor ) {
		if ( !$descriptor ) {
			return true;
		}
		$selectedNamespace = '-';
		// "wpDestFile" is set on query string. e.g after click on redlink or on re-upload
		if ( !empty( $descriptor['DestFile']['default'] ) ) {
			$target = $descriptor['DestFile']['default'];
			$target = str_replace( '_', ' ', $target );
			$targetPieces = explode( ':', $target );
			$nsText = '';
			if ( count( $targetPieces ) > 1 ) {
				$nsText = str_replace( ' ', '_', $targetPieces[0] );
				$target = $targetPieces[1];
			}
			$descriptor['DestFile']['default'] = $target;
			$selectedNamespace = $nsText;
		}
		$namespaceList = new NamespaceList(
			$this->context->getUser(),
			$this->nsfrConfig,
			$this->context->getLanguage()
		);
		$namespaceSelectOptions = [];
		foreach ( $namespaceList->getEditable() as $nsId => $namespace ) {
			$namespaceSelectOptions[$namespace->getDisplayName()]
				= $namespace->getCanonicalName();
			if ( $nsId === NS_MAIN ) {
				$namespaceSelectOptions[$namespace->getDisplayName()] = '-';
			}
		}
		$fieldDef = [
			'NSFR_Namespace' => [
				'label'    => wfMessage( 'namespace' )->plain(),
				'section'  => 'description',
				'class'    => HTMLSelectField::class,
				'options'  => $namespaceSelectOptions,
				'required' => true,
				'default' => $selectedNamespace
			],
			'NSFR_DestFile' => [
				'type' => 'text',
				'section' => 'description',
				'label-message' => 'nsfilerepo-upload-target',
				'size' => 60,
				'default' => '',
				'readonly' => true,
				'nodata' => false,
			],
		];
		// Prevent change of this fields value when it's a reupload
		if ( isset( $descriptor['ForReUpload'] ) ) {
			$fieldDef['NSFR_Namespace']['disabled'] = true;
			$fieldDef['NSFR_Namespace']['help-message']
				= 'nsfilerepo-reupload-namespaceselector-disabled-helptext';
		}
		$descriptor = $descriptor + [
			'NSFR_Namespace' => $fieldDef['NSFR_Namespace'],
			'NSFR_DestFile' => $fieldDef['NSFR_DestFile'],
		];
		return true;
	}
}
