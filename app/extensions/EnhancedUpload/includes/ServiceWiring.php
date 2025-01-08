<?php

use MediaWiki\Extension\EnhancedUpload\AttachmentTagModifier;
use MediaWiki\MediaWikiServices;

return [
	'EnhancedUploadAttachmentTagModifier' => static function ( MediaWikiServices $services ) {
		$titleFactory = $services->getTitleFactory();
		return new AttachmentTagModifier( $titleFactory );
	},
];
