window.ext = window.ext || {};
window.ext.bookshelf = window.ext.bookshelf || {};
window.ext.bookshelf.ui = window.ext.bookshelf.ui || {};
window.ext.bookshelf.ui.data = window.ext.bookshelf.ui.data || {};

ext.bookshelf.ui.data.BookNumberProcessor = function () {};

OO.initClass( ext.bookshelf.ui.data.BookNumberProcessor );

ext.bookshelf.ui.data.BookNumberProcessor.prototype.calculateNumbersFromList = function ( items ) {
	const numbering = [];
	const counters = [ 0 ];

	items.forEach( ( item ) => {
		const level = item.level;
		while ( counters.length < level ) {
			counters.push( 0 );
		}
		counters[ level - 1 ]++;
		for ( let i = level; i < counters.length; i++ ) {
			counters[ i ] = 0;
		}
		numbering.push( counters.slice( 0, level ).join( '.' ) + '.' );
	} );

	return numbering;
};

ext.bookshelf.ui.data.BookNumberProcessor.prototype.calculateNumberForElement = function ( items, item ) {
	let result = null;

	function processItems( currentItems, prefix = '' ) {
		let count = 0;

		for ( let i = 0; i < currentItems.length; i++ ) {
			count++;
			const currentNumber = prefix ? `${ prefix }.${ count }` : `${ count }`;
			const currentItem = currentItems[ i ];

			if ( currentItem === item ) {
				result = currentNumber;
				return;
			}

			if ( currentItem.items && currentItem.items.length > 0 ) {
				processItems( currentItem.items, currentNumber );
				if ( result ) {
					return;
				} // Stop processing if the item is found
			}
		}
	}

	processItems( items );
	return result;
};
