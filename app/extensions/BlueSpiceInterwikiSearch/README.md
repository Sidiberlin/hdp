# BlueSpiceInterwikiSearch

## Requirements

- BlueSpice >= 3.0.0
- BlueSpiceExtendedSearch installed and configured on all wiki instances involved

## Installation
Execute

    composer require bluespice/interwikisearch dev-REL1_31
within MediaWiki root or add `bluespice/interwikisearch` to the
`composer.json` file of your project

## Activation
Add

    wfLoadExtension( 'BlueSpiceInterwikiSearch' );
to your `LocalSettings.php` or the appropriate `settings.d/` file.

# Configuration
## Configuration variables

- `$bsgInterwikiSearchSearchInOtherWikis = true|false` - enable/disable Interwiki search 
- `$bsgInterwikiSearchSilentOnError = true|false` - control whether failure to retrieve results from target wiki will be shown to the user. Errors will be hidden if se to `true`.
This global setting can be overriden for individual targets by specifying `silent-on-erros` in target configuration.
- `$bsgInterwikiSearchSources` - target specification. See next chapter

## Setting up targets

Targets are configured in `$bsgInterwikiSearchSources` global variable.
There are several way of configuring this variable from easier and less flexible to more complex and more flexible.

- Just specifying display name and base URL, all properties are generated from these values
        
        $bsgInterwikiSearchSources = [
            'My Wiki' => 'http://my-wiki'
        ]
    
- Specifying base URL and additional properties. All other necessary URLs will be generated automatically, based on base URL

         $bsgInterwikiSearchSources = [
            'wiki1' => [
                   'base' => 'http://my-wiki',
                   'name' => 'My test wiki',
                   'silent-on-errors' => true,
                   'public-wiki' => true,
                   'same-domain' => true
            ]
        ]

- Full specification, should mainly be used when all necessary URLs differ and cannot be created from a single base URL    
    
        $bsgInterwikiSearchSources = [
            'wiki1' => [
                    'api-endpoint' => 'http://my-wiki/api.php',
                    'search-on-wiki-url' => 'http://my-wiki/index.php?title=Special:SearchCenter&q=$1',
                    'name' => 'My test wiki',
                    'silent-on-errors' => true
                    'public-wiki' => true,
                    'same-domain' => true
            ]
        ]

### Configuration description

- `base` Base URL of the target wiki. Based on this value, other URLs will be generated. For URL `http://mydomain/wiki/index.php` base URL would be `http://mydomain`. Do not use if `api-endpoint` or `search-on-wiki-url` are set.
- `api-endpoint` Full URL of the target wiki's API endpoint. Use only when `base` is not set
- `search-on-wiki-url` Full URL of the target wiki's Special:SearchCenter page. Use only when `base` is not set
- `name` Display name of the target wiki that will be shown in the UI
- `silent-on-error` If true, targets that report an error will be skipped, and won't show up in the UI at all
- `public-wiki` Set to true if wiki is not read-locked and can be accessed without logging in. Defaults to `false`.
- `same-domain` Set to true if target wiki is on the same base domain as the wiki you are searching from. Should be set only when `public-wiki` is not set or set to false.

## Searching restricted wikis

!!! Not required if target wikis are on the same base domain as the wiki search is executed from !!!

Wikis that are publicly available (non-logged users can read) and that have `public-wiki = true` set in its target configuration, can be searched without any further configurations.

However, searching restricted wikis is a bit more difficult. There are two criteria that need to be met in order for restricted wiki to be searchable:

- User must have an active session to the target wiki in browser where search is being done
    
    If user wants to search a restricted wikis, without logging into that wiki first, error will be shown prompting user to log in, after which
    search can be continued. This does not apply to Kerberos (or similar network-based authentication, as the login process
    will be done automatically on first request).
-  Target wiki must accept request from source wiki (CORS).
    Fortunatelly, MediaWiki offers a mechanism to allow specifying allowed domains.
    If we have Wiki1 at `http://wiki.on.domainone.com` and Wiki2 at `http://wiki.on.domaintwo.com`, and we want to setup Wiki1 to search Wiki2, we need to add following configuration to Wiki2 (target):
    
    `$wgCrossSiteAJAXdomains = [ 'wiki.on.domainone.com' ]`
    
    If Wiki2 is searchable from multiple sources, add every one into this variable. If multiple sources are all subdomains of the same main domain, this setting can look like:
    
    `$wgCrossSiteAJAXdomains = [ '*.mydomain.com' ]`

