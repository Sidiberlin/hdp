# ChatBot

## Installation
Execute

    composer require hallowelt/chatbot dev-REL1_35
within MediaWiki root or add `hallowelt/chatbot` to the
`composer.json` file of your project

## Activation
Add

    wfLoadExtension( 'ChatBot' );
to your `LocalSettings.php` or the appropriate `settings.d/` file.