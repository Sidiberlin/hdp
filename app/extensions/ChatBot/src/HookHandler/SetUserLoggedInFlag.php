<?php

namespace ChatBot\HookHandler;

use MediaWiki\Auth\Hook\UserLoggedInHook;
use MediaWiki\Hook\MakeGlobalVariablesScriptHook;
use RequestContext;

class SetUserLoggedInFlag implements UserLoggedInHook, MakeGlobalVariablesScriptHook {

	/**
	 * @inheritDoc
	 */
	public function onUserLoggedIn( $user ) {
		RequestContext::getMain()->getRequest()->getSession()->set( 'ChatBotUserLoggedIn', true );
	}

	/**
	 * @inheritDoc
	 */
	public function onMakeGlobalVariablesScript( &$vars, $out ): void {
		$flag = RequestContext::getMain()->getRequest()->getSession()->get( 'ChatBotUserLoggedIn', false );

		if ( $flag ) {
			$vars['wgChatBotUserLoggedIn'] = true;
			RequestContext::getMain()->getRequest()->getSession()->remove( 'ChatBotUserLoggedIn' );
		}
	}
}
