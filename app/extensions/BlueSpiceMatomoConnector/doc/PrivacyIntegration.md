# Integrate Matomo opt-out into BlueSpicePrivacy

In order to control Matomo tracking policy using BlueSpicePrivacy, your Matomo installation requires "AjaxOptOut" plugin
module to be installed and activated

Download the plugin from https://plugins.matomo.org/AjaxOptOut and copy it to `/plugins` directory.
Then go to the plugin management page in Matomo and enable the plugin

Once the plugin is available, set

    $GLOBALS['$bsgMatomoBlueSpicePrivacyIntegration'] = true;

or use Special:BlueSpiceConfigManager

to enable Privacy integration
