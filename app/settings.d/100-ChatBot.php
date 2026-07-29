<?php

// ChatBot extension — point at local Haystack proxy instead of Deepset Cloud
// The proxy (docker service "chatbot-proxy") translates Deepset API format
// to Haystack hayhooks format.

$GLOBALS['BmbfDeepsetApiChatUrl'] = 'http://chatbot-proxy:8080';
$GLOBALS['BmbfDeepsetApiSearchSessionsUrl'] = 'http://chatbot-proxy:8080/session';
$GLOBALS['BmbfDeepsetApiKey'] = 'not-needed';
$GLOBALS['BmbfDeepsetApiIndexUrl'] = '';
$GLOBALS['BmbfDeepsetApiFeedbackUrl'] = '';
$GLOBALS['BmbfDeepsetApiTagUrl'] = '';
$GLOBALS['BmbfDeepsetPipelineStatsUrl'] = '';
