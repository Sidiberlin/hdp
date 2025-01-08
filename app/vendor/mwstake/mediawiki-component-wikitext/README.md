## MediaWiki Stakeholders Group - Components
# WikiText for MediaWiki

**This code is meant to be executed within the MediaWiki application context. No standalone usage is intended.**

## Use in a MediaWiki extension

**MediaWiki 1.35**

- Add `"mwstake/mediawiki-component-wikitext": "~5"` to the `require` section of your `composer.json` file.

**MediaWiki 1.39**

- Add `"mwstake/mediawiki-component-wikitext": "~6"` to the `require` section of your `composer.json` file.

Since 2.0 explicit initialization is required. This can be achived by
- either adding `"callback": "mwsInitComponents"` to your `extension.json`/`skin.json`
- or calling `mwsInitComponents();` within you extensions/skins custom `callback` method

See also [`mwstake/mediawiki-componentloader`](https://github.com/hallowelt/mwstake-mediawiki-componentloader).

## Available Services
- `MWStakeWikitextParserFactory`
- `MWStakeWikitextNodeProcessorRegistryFactory`

## Using the `MenuParser`

```php
/* @var MediaWiki\Revision\RevisionRecord */
$revision = $this->getRevision();

/* @var MWStake\MediaWiki\Component\Wikitext\ParserFactory */
$parserFactory = MediaWiki\MediaWikiServices::getInstance()->get( 'MWStakeWikitextParserFactory' );

/* @var MWStake\MediaWiki\Component\Wikitext\Parser\MenuParser */
$menuParser = $parserFactory->newMenuParser( $revision );

/* @var MWStake\MediaWiki\Component\Wikitext\INode[] */
$nodes = $parser->parse();
```
