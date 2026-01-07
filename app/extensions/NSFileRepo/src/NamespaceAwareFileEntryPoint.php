<?php

namespace MediaWiki\Extension\NSFileRepo;

use File;
use MediaWiki\Context\IContextSource;
use MediaWiki\EntryPointEnvironment;
use MediaWiki\FileRepo\AuthenticatedFileEntryPoint;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\Html\TemplateParser;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use Wikimedia\FileBackend\HTTPFileStreamer;

class NamespaceAwareFileEntryPoint extends AuthenticatedFileEntryPoint {

	/**
	 * @var NSFileRepoHelper
	 */
	private $namespaceFileHelper;

	/**
	 * @var array List of extensions to force download
	 */
	private $forceDownload;

	/**
	 * @param IContextSource $context
	 * @param EntryPointEnvironment $environment
	 * @param MediaWikiServices $mediaWikiServices
	 * @param NSFileRepoHelper $namespaceFileHelper
	 * @param array $forceDownloadForExtensions
	 */
	public function __construct(
		IContextSource $context, EntryPointEnvironment $environment, MediaWikiServices $mediaWikiServices,
		NSFileRepoHelper $namespaceFileHelper, array $forceDownloadForExtensions
	) {
		parent::__construct( $context, $environment, $mediaWikiServices );
		$this->namespaceFileHelper = $namespaceFileHelper;
		$this->forceDownload = $forceDownloadForExtensions;
	}

	/**
	 * Main entry point
	 */
	public function execute() {
		$services = $this->getServiceContainer();
		$permissionManager = $services->getPermissionManager();

		$request = $this->getRequest();
		// HINT: We intentionally disable "public wiki" logic in this entry point, because it
		// is not considered a valid use case for this extension. The only thing it could have
		// been used for is to remove headers that break (CDN) caching. But given the nature
		// of this extension, we also don't want files delivered by it to be cached by a CDN
		// in any case.
		// $publicWiki = $services->getGroupPermissionsLookup()->groupHasPermission( '*', 'read' );

		// Find the path assuming the request URL is relative to the local public zone URL
		$baseUrl = $services->getRepoGroup()->getLocalRepo()->getZoneUrl( 'public' );
		if ( $baseUrl[0] === '/' ) {
			$basePath = $baseUrl;
		} else {
			$basePath = parse_url( $baseUrl, PHP_URL_PATH );
		}
		$path = $this->getRequestPathSuffix( "$basePath" );

		if ( $path === false ) {
			// Try instead assuming img_auth.php is the base path
			$basePath = $this->getConfig( MainConfigNames::ImgAuthPath )
				?: $this->getConfig( MainConfigNames::ScriptPath ) . '/img_auth.php';
			$path = $this->getRequestPathSuffix( $basePath );
		}

		if ( $path === false ) {
			$this->forbidden( 'img-auth-accessdenied', 'img-auth-notindir' );
			return;
		}

		if ( $path === '' || $path[0] !== '/' ) {
			// Make sure $path has a leading /
			$path = "/" . $path;
		}

		$user = $this->getContext()->getUser();

		// Various extensions may have their own backends that need access.
		// Check if there is a special backend and storage base path for this file.
		$pathMap = $this->getConfig( MainConfigNames::ImgAuthUrlPathMap );
		foreach ( $pathMap as $prefix => $storageDir ) {
			$prefix = rtrim( $prefix, '/' ) . '/';
			if ( strpos( $path, $prefix ) === 0 ) {
				$be = $services->getFileBackendGroup()->backendFromPath( $storageDir );
				$filename = $storageDir . substr( $path, strlen( $prefix ) );
				// Check basic user authorization
				$isAllowedUser = $permissionManager->userHasRight( $user, 'read' );
				if ( !$isAllowedUser ) {
					$this->forbidden( 'img-auth-accessdenied', 'img-auth-noread', $path );
					return;
				}
				if ( $be && $be->fileExists( [ 'src' => $filename ] ) ) {
					wfDebugLog( 'img_auth', "Streaming `" . $filename . "`." );
					$be->streamFile( [
						'src' => $filename,
						'headers' => [ 'Cache-Control: private', 'Vary: Cookie' ]
					] );
				} else {
					$this->forbidden( 'img-auth-accessdenied', 'img-auth-nofile', $path );
				}

				return;
			}
		}

		// Get the local file repository
		$repo = $services->getRepoGroup()->getLocalRepo();
		$zone = strstr( ltrim( $path, '/' ), '/', true );

		// Get the full file storage path and extract the source file name.
		// (e.g. 120px-Foo.png => Foo.png or page2-120px-Foo.png => Foo.png).
		// This only applies to thumbnails/transcoded, and each of them should
		// be under a folder that has the source file name.
		if ( $zone === 'thumb' || $zone === 'transcoded' ) {
			$name = $this->detectNamespace( wfBaseName( dirname( $path ) ), $path );
			$filename = $repo->getZonePath( $zone ) . substr( $path, strlen( "/" . $zone ) );
			// Check to see if the file exists
			if ( !$repo->fileExists( $filename ) ) {
				$this->forbidden( 'img-auth-accessdenied', 'img-auth-nofile', $filename );
				return;
			}
		} else {
			$name = $this->detectNamespace( wfBaseName( $path ), $path );
			$filename = $repo->getZonePath( 'public' ) . $path;

			// Check to see if the file exists and is not deleted
			$bits = explode( '!', $name, 2 );
			if ( str_starts_with( $path, '/archive/' ) && count( $bits ) == 2 ) {
				$file = $repo->newFromArchiveName( $bits[1], $name );
			} else {
				$file = $repo->newFile( $name );
			}
			if ( !$file || !$file->exists() || $file->isDeleted( File::DELETED_FILE ) ) {
				$this->forbidden( 'img-auth-accessdenied', 'img-auth-nofile', $filename );
				return;
			}
		}

		$headers = [];
		$title = $services->getTitleFactory()->makeTitleSafe( NS_FILE, $name );
		if ( !$title instanceof Title ) {
			// files have valid titles
			$this->forbidden( 'img-auth-accessdenied', 'img-auth-badtitle', $name );
			return;
		}

		$hookRunner = new HookRunner( $services->getHookContainer() );

		// For private wikis, run extra auth checks and set cache control headers
		$headers['Cache-Control'] = 'private';
		$headers['Vary'] = 'Cookie';

		// Run hook for extension authorization plugins
		$authResult = [];
		if ( !$hookRunner->onImgAuthBeforeStream( $title, $path, $name, $authResult ) ) {
			$this->forbidden( $authResult[0], $authResult[1], array_slice( $authResult, 2 ) );
			return;
		}

		if ( !$this->authenticateNamespaceTitle( $path, $user ) ) {
			$this->forbidden( 'img-auth-accessdenied', 'img-auth-noread', $name );
			return;
		}

		$range = $this->environment->getServerInfo( 'HTTP_RANGE' );
		$ims = $this->environment->getServerInfo( 'HTTP_IF_MODIFIED_SINCE' );

		if ( $range !== null ) {
			$headers['Range'] = $range;
		}
		if ( $ims !== null ) {
			$headers['If-Modified-Since'] = $ims;
		}

		$forceDownload = false;
		foreach ( $this->forceDownload as $ext ) {
			$quotedExt = preg_quote( ".$ext" );
			$endsWithPattern = "#$quotedExt$#si";
			if ( preg_match( $endsWithPattern, $filename ) === 1 ) {
				$forceDownload = true;
				break;
			}
		}

		if ( $request->getCheck( 'download' ) || $forceDownload ) {
			$headers['Content-Disposition'] = 'attachment';
		}

		// Allow modification of headers before streaming a file
		$hookRunner->onImgAuthModifyHeaders( $title->getTitleValue(), $headers );

		// Stream the requested file
		$this->prepareForOutput();

		[ $headers, $options ] = HTTPFileStreamer::preprocessHeaders( $headers );
		wfDebugLog( 'img_auth', "Streaming `" . $filename . "`." );
		$repo->streamFileWithStatus( $filename, $headers, $options );

		$this->enterPostSendMode();
	}

	/**
	 * Issue a standard HTTP 403 Forbidden header ($msg1-a message index, not a message) and an
	 * error message ($msg2, also a message index), (both required) then end the script
	 * subsequent arguments to $msg2 will be passed as parameters only for replacing in $msg2
	 *
	 * @param string $msg1
	 * @param string $msg2
	 * @param mixed ...$args To pass as params to $context->msg() with $msg2. Either variadic, or a single
	 *   array argument.
	 */
	private function forbidden( $msg1, $msg2, ...$args ) {
		$args = ( isset( $args[0] ) && is_array( $args[0] ) ) ? $args[0] : $args;
		$context = $this->getContext();

		$msgHdr = $context->msg( $msg1 )->text();
		$detailMsg = $this->getConfig( MainConfigNames::ImgAuthDetails )
			? $context->msg( $msg2, $args )->text()
			: $context->msg( 'badaccess-group0' )->text();

		wfDebugLog(
			'img_auth',
			"wfForbidden Hdr: " . $context->msg( $msg1 )->inLanguage( 'en' )->text()
			. " Msg: " . $context->msg( $msg2, $args )->inLanguage( 'en' )->text()
		);

		$this->status( 403 );
		$this->header( 'Cache-Control: no-cache' );
		$this->header( 'Content-Type: text/html; charset=utf-8' );
		$language = $context->getLanguage();
		$lang = $language->getHtmlCode();
		$this->header( "Content-Language: $lang" );
		$templateParser = new TemplateParser();
		$this->print(
			$templateParser->processTemplate( 'ImageAuthForbidden', [
				'dir' => $language->getDir(),
				'lang' => $lang,
				'msgHdr' => $msgHdr,
				'detailMsg' => $detailMsg,
			] )
		);
	}

	/**
	 * Add namespace prefix if detected in path
	 *
	 * @param string $name
	 * @param string $path
	 * @return string
	 */
	private function detectNamespace( string $name, string $path ): string {
		$title = $this->namespaceFileHelper->getTitleFromPath( $path );
		if ( $title instanceof Title && $title->getNamespace() !== NS_MAIN ) {
			$bits = explode( '!', $name, 2 );
			if ( count( $bits ) === 2 ) {
				return $bits[0] . '!' . $title->getNsText() . ':' . $bits[1];
			}

			// Not using "$title->getPrefixedDBKey()" because "$wgCapitalLinkOverrides[NS_FILE]" may be "false"
			return $title->getNsText() . ':' . $name;
		}

		return $name;
	}

	/**
	 * @param string $path
	 * @param User $user
	 * @return bool
	 */
	private function authenticateNamespaceTitle( string $path, User $user ) {
		$nsfrHelper = new NSFileRepoHelper();
		$authTitle = $nsfrHelper->getTitleFromPath( $path );

		if ( $authTitle instanceof Title === false ) {
			return false;
		}

		$permissionManager = $this->getServiceContainer()->getPermissionManager();
		if ( !$permissionManager->userCan( 'read', $user, $authTitle ) ) {
			return false;
		}

		return true;
	}

}
