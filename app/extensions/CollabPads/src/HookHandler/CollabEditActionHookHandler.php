<?php

namespace MediaWiki\Extension\CollabPads\HookHandler;

use MediaWiki\Extension\CollabPads\CollabPadAccessTokenDAO;
use MediaWiki\Hook\MediaWikiPerformActionHook;
use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Message\Message;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Request\WebRequest;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use PermissionsError;
use Wikimedia\Rdbms\LoadBalancer;

class CollabEditActionHookHandler implements
	SkinTemplateNavigation__UniversalHook,
	MediaWikiPerformActionHook
{

	/** @var NamespaceInfo */
	private $namespaceInfo;

	/** @var PermissionManager */
	private $permissionManager;

	/** @var LoadBalancer */
	private $loadBalancer;

	/**
	 * @param NamespaceInfo $namespaceInfo
	 * @param PermissionManager $permissionManager
	 * @param LoadBalancer $loadBalancer
	 */
	public function __construct(
		NamespaceInfo $namespaceInfo, PermissionManager $permissionManager, LoadBalancer $loadBalancer
	) {
		$this->namespaceInfo = $namespaceInfo;
		$this->permissionManager = $permissionManager;
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * // phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
	 * @param \SkinTemplate $skinTemplate
	 * @param array &$links
	 */
	public function onSkinTemplateNavigation__Universal( $skinTemplate, &$links ): void {
		$title = $skinTemplate->getSkin()->getRelevantTitle();
		if ( !$title ) {
			return;
		}

		if ( $title->getContentModel() !== CONTENT_MODEL_WIKITEXT ) {
			return;
		}

		$namespace = $title->getNamespace();
		$contentNamespaces = $this->namespaceInfo->getContentNamespaces();
		if ( !in_array( $namespace, $contentNamespaces ) ) {
			return;
		}

		$veTab = [
			'href' => $title->getLocalURL( [ 'veaction' => 'collab-edit' ] ),
			'text' => Message::newFromKey( 'collabpads-content-action-text' )->text(),
			'title' => Message::newFromKey( 'collabpads-content-action-tooltip' )->text(),
			'primary' => true,
			'class' => '',
		];
		$links['actions']['collabpad'] = $veTab;
	}

	/**
	 * @param OutputPage $output
	 * @param Article $article
	 * @param Title $title
	 * @param User $user
	 * @param WebRequest $request
	 * @param MediaWiki $mediaWiki
	 * @return bool|void
	 */
	public function onMediaWikiPerformAction( $output, $article, $title, $user, $request, $mediaWiki ) {
		$action = $request->getText( 'action', $request->getText( 'veaction', 'view' ) );

		if ( $action === 'collab-edit' ) {
			$this->ensureUserCanEdit( $user, $title );

			// Add "access token" here, to be able to use it on frontend
			$token = $this->makeCollabPadAccessToken( $user, $title );

			$output->addJsConfigVars( 'wgCollabPadsUserAccessToken', $token );

			$output->addModules( [
				'ext.collabPads',
				'ext.collabpads.title'
			] );

			return true;
		}
	}

	/**
	 * Ensure that the user has the 'edit' permission for the given title
	 *
	 * @param User $user
	 * @param Title $title
	 * @throws PermissionsError if the user does not have the necessary permission
	 */
	private function ensureUserCanEdit( User $user, Title $title ): void {
		$userCanEdit = $this->permissionManager->userCan( 'edit', $user, $title );
		if ( !$userCanEdit ) {
			$editPermissionErrors = $this->permissionManager->getPermissionErrors( 'edit', $user, $title );
			throw new PermissionsError( 'edit', $editPermissionErrors );
		}
	}

	/**
	 * Generate, save to DB and return CollabPad "access token" for current user
	 *
	 * @param User $user
	 * @param Title $title
	 * @return string User "access token"
	 *
	 * @see \MediaWiki\Extension\CollabPads\Api\Rest\AccessUserHandler::run
	 */
	private function makeCollabPadAccessToken( User $user, Title $title ): string {
		$userId = $user->getId();
		$titlePrefixedDbKey = $title->getPrefixedDBkey();

		$accessTokenDAO = new CollabPadAccessTokenDAO( $this->loadBalancer );

		// There may be cases when token was created but for some reason was not removed from DB
		// For example if user leaves the page before actual connecting to the session etc.
		// So at first we should check if any token for these specific user and title already exists
		$token = $accessTokenDAO->get( $userId, $titlePrefixedDbKey );
		if ( $token ) {
			return $token;
		}

		return $accessTokenDAO->create( $userId, $titlePrefixedDbKey );
	}

	/**
	 * @param array &$tags
	 * @return bool|void
	 */
	public static function onRegisterTags( array &$tags ) {
		$tags = array_merge( $tags, [ 'collab-edit' ] );
		return true;
	}

}
