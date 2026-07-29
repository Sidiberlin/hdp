<?php

// ChatBot extension — point at local Haystack proxy instead of Deepset Cloud
// The proxy (docker service "chatbot-proxy") translates Deepset API format
// to Haystack hayhooks format.
//
// IMPORTANT: MediaWiki's default GlobalVarConfig reads $wg-prefixed globals
// (this extension declares no config_prefix override in extension.json, so
// it uses MediaWiki's standard "wg" prefix). Setting the bare, unprefixed
// $GLOBALS key (e.g. $GLOBALS['BmbfDeepsetApiChatUrl']) silently does
// nothing — $config->get('BmbfDeepsetApiChatUrl') keeps returning the
// extension.json default (""), and the ChatBot UI fails with
// "The scheme '' is not supported." on every chat request.

$GLOBALS['wgBmbfDeepsetApiChatUrl'] = 'http://chatbot-proxy:8080';
$GLOBALS['wgBmbfDeepsetApiSearchSessionsUrl'] = 'http://chatbot-proxy:8080/session';
$GLOBALS['wgBmbfDeepsetApiKey'] = 'not-needed';
$GLOBALS['wgBmbfDeepsetApiIndexUrl'] = '';
$GLOBALS['wgBmbfDeepsetApiFeedbackUrl'] = '';
$GLOBALS['wgBmbfDeepsetApiTagUrl'] = '';
$GLOBALS['wgBmbfDeepsetPipelineStatsUrl'] = '';
