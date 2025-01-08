var Vue = require( 'vue' ),
	Tiles = require( './Tiles.vue' ),
	$allRLModules = [];

function getList() {
	var dfd = $.Deferred();
	$.ajax( {
		url: mw.util.wikiScript( 'rest' ) + '/unifiedtaskoverview/list',
		contentType: "application/json",
		dataType: 'json'
	} ).done( function( response ) {
		dfd.resolve( response );
	} ).fail ( function ( jgXHR, type, status ) {
		if ( type === 'error' ) {
			dfd.reject( {
				error: jgXHR.responseJSON || jgXHR.responseText
			} );
		}
		dfd.reject( { type: type, status: status } );
	} );
	return dfd.promise();
}

function loadData( response) {
	loadRLModules( response );
	var itemData = sortItems( response );
	setVisibility( itemData );
	return itemData;
}

function sortItems( data ) {
	data = data.sort( ( a, b ) => a.sortkey - b.sortkey );
	return data;
}

// get RLModules from data
function loadRLModules ( data ) {
	for ( var x = 0; x < data.length; x++ ) {
		data[x][ 'RLmodules' ].forEach( function ( item ) {
			if ($allRLModules.length === 0 ) {
				$allRLModules.push( item );
			} else {
				$allRLModules.forEach( function ( module ) {
					if ( module !== item ) {
						$allRLModules.push( item );
					}
				});
			}
		});
	}
	mw.loader.using( $allRLModules );
}

function setDataForSearch ( items ) {
	var dataSearch = [];
	items.forEach( function ( item )  {
		dataSearch.push( item.type.toLowerCase() + " "
			+ item.header.toLowerCase() + " " + item.subheader.toLowerCase() + " "
			+ item.body.toLowerCase() );
	});
	return dataSearch;
}

function setVisibility ( items ) {
	items.forEach( function ( item )  {
		item.isVisible = true;
	});
}

function render() {
	var deferred = $.Deferred();
	var dfdList = getList();
	dfdList.done( function( response ) {
		var h = Vue.h;
		var vm = Vue.createMwApp( {
			mounted: function () {
				deferred.resolve( this.$el );
			},
			render: function () {
				var listItems = [];
				var searchElts = [];
				if ( response.length > 0 ) {
					listItems = loadData( response );
					searchElts = setDataForSearch( listItems );
				}

				return h( Tiles, {
					items: listItems,
					searchElements: searchElts,
					noTaskDesc: listItems.length == 0,
					searchPlaceholderLabel: mw.message( 'unifiedtaskoverview-search-placeholder' ).plain()
				} );
			}
		} );
		vm.mount( '#unifiedTaskOverview-tiles');
		return deferred;
	} );
}

render();
