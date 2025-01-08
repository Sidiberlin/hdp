# ContentProvisioning

## Installation
Execute

    composer require hallowelt/contentprovisioning dev-REL1_39
within MediaWiki root or add `hallowelt/contentprovisioning` to the
`composer.json` file of your project

## Activation
Add

    wfLoadExtension( 'ContentProvisioning' );
to your `LocalSettings.php` or the appropriate `settings.d/` file.
