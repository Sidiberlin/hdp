const customDimensionValues = mw.config.get( 'bsgMatomoConnectorCustomDimensionValues' );
const baseUrl = mw.config.get( 'bsgMatomoConnectorBaseUrl' );
const siteID = mw.config.get( 'bsgMatomoConnectorsiteID' );

window._paq = window._paq || []; // eslint-disable-line no-underscore-dangle

// Set site ID and tracker URL
_paq.push( [ 'setSiteId', siteID ] );
_paq.push( [ 'setTrackerUrl', `${ baseUrl }/matomo.php` ] );

// Track page categories
const categories = mw.config.get( 'wgCategories', [] );
categories.forEach( ( category ) => {
	_paq.push( [ 'trackEvent', 'page_category', 'page_category_view', category ] );
} );

// Track user groups
let userGroups = mw.config.get( 'wgUserGroups', [] );
if ( userGroups.includes( 'user' ) ) {
	// Logged in: user = _loggedin
	userGroups = userGroups.filter( ( group ) => group !== '*' );
	userGroups = userGroups.map( ( group ) => group === 'user' ? '_loggedin' : group );
} else {
	// Anonymous: * = _anonymous
	userGroups = [ '_anonymous' ];
}

userGroups.forEach( ( group ) => {
	_paq.push( [ 'trackEvent', 'user_group', 'user_group_visit', group ] );
} );

// Set custom dimensions
customDimensionValues.forEach( ( dim ) => {
	if ( dim.id && dim.value ) {
		_paq.push( [ 'setCustomDimension', parseInt( dim.id, 10 ), dim.value ] );
	}
} );

// ExtendedSearch site search tracking
$( '#bs-es-results' ).on( 'resultsReady', () => {
	const lookup = bs.extendedSearch.SearchCenter.getLookupObject();
	const term = lookup?.query?.bool?.must?.[ 0 ]?.query_string?.query;

	if ( term ) {
		_paq.push( [ 'trackSiteSearch', term ] );
	}
} );

// Standard tracking
_paq.push( [ 'trackPageView' ] );
_paq.push( [ 'enableLinkTracking' ] );

// Load Matomo JS last
const script = document.createElement( 'script' );
script.type = 'text/javascript';
script.async = true;
script.src = `${ baseUrl }/matomo.js`;
document.head.appendChild( script );
