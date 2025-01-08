<?php

namespace BlueSpice\Discovery\Component;

use BlueSpice\Discovery\BreadcrumbDataProviderFactory;
use BlueSpice\Discovery\Renderer\DefaultBreadCrumbRenderer;
use MessageLocalizer;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\Literal;
use Title;

class DefaultBreadcrumbNav extends Literal {

	/**
	 *
	 * @var Title
	 */
	private $title = null;

	/**
	 *
	 * @var User
	 */
	private $user = null;

	/**
	 *
	 * @var MessageLocalizer
	 */
	private $messageLocalizer = null;

	/**
	 *
	 * @var SpecialPageFactory
	 */
	private $specialPageFactory = null;

	/**
	 *
	 * @var NamespaceInfo
	 */
	private $namespaceInfo = null;

	/**
	 * @var BreadcrumbDataProviderFactory
	 */
	private $breadcrumbFactory = null;

	/**
	 *
	 * @param Title $title
	 * @param User $user
	 * @param MessageLocalizer $messageLocalizer
	 * @param SpecialPageFactory $specialPageFactory
	 * @param NamespaceInfo $namespaceInfo
	 * @param BreadcrumbDataProviderFactory $breadcrumbFactory
	 */
	public function __construct( $title, $user, $messageLocalizer, $specialPageFactory,
		$namespaceInfo, $breadcrumbFactory ) {
		$this->title = $title;
		$this->user = $user;
		$this->messageLocalizer = $messageLocalizer;
		$this->specialPageFactory = $specialPageFactory;
		$this->namespaceInfo = $namespaceInfo;
		$this->breadcrumbFactory = $breadcrumbFactory;
	}

	/**
	 * Raw HTML string
	 *
	 * @return string
	 */
	public function getHtml(): string {
		return $this->buildHtml();
	}

	private function buildHtml(): string {
		$renderer = new DefaultBreadCrumbRenderer( $this->title, $this->user, $this->messageLocalizer,
			$this->specialPageFactory, $this->namespaceInfo, $this->breadcrumbFactory );

		return $renderer->getHtml();
	}

}
