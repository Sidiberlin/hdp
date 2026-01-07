( function ( mw, $, bs ) {

	bs.util.registerNamespace( 'bs.bookshelf.object' );

	bs.bookshelf.object.BookPDFDroplet = function ( cfg ) {
		bs.bookshelf.object.BookPDFDroplet.parent.call( this, cfg );
	};

	OO.inheritClass( bs.bookshelf.object.BookPDFDroplet, ext.contentdroplets.object.TransclusionDroplet );

	bs.bookshelf.object.BookPDFDroplet.prototype.templateMatches = function ( templateData ) {
		if ( !templateData ) {
			return false;
		}
		const target = templateData.target.wt;
		return target.trim( '\n' ) === 'BookPDFLink';
	};

	bs.bookshelf.object.BookPDFDroplet.prototype.toDataElement = function ( domElements, converter ) { // eslint-disable-line no-unused-vars
		return false;
	};

	bs.bookshelf.object.BookPDFDroplet.prototype.getFormItems = function () {
		const config = require( './config.json' );
		const templates = [];

		for ( const entry in config.templates ) {
			const item = {
				data: encodeURI( config.templates[ entry ] ),
				label: config.templates[ entry ]
			};
			templates.push( item );
		}
		let templateSelectDisabled = false;
		if ( templates.length === 1 ) {
			templateSelectDisabled = true;
		}

		return [
			{
				name: 'book',
				label: mw.message( 'bs-bookshelf-droplet-bookpdf-book-label' ).text(),
				type: 'title',
				namespace: 1504
			},
			{
				name: 'template',
				label: mw.message( 'bs-bookshelf-droplet-bookpdf-template-label' ).text(),
				type: 'dropdown',
				default: config.default,
				options: templates,
				disabled: templateSelectDisabled
			},
			{
				name: 'label',
				label: mw.message( 'bs-bookshelf-droplet-bookpdf-link-label' ).text(),
				type: 'text'
			}
		];
	};

	ext.contentdroplets.object.TransclusionDroplet.prototype.updateMWData =
	function ( newData, mwData ) {
		newData = newData || {};

		const template = ( mwData.hasOwnProperty( 'parts' ) && mwData.parts.length > 0 &&
			mwData.parts[ 0 ].hasOwnProperty( 'template' ) ) ? mwData.parts[ 0 ].template : null;
		let key;
		if ( !template ) {
			return mwData;
		}
		for ( key in template.params ) {

			if ( !template.params.hasOwnProperty( key ) ) {
				continue;
			}
			if ( key === 'book' ) {
				if ( typeof template.params[ key ] === 'string' && template.params[ key ].length > 0 ) {
					const bookTitle = mw.Title.newFromText( template.params[ key ], bs.ns.NS_BOOK );
					if ( bookTitle ) {
						template.params[ key ] = bookTitle.getPrefixedText();
					}
				}
				if ( typeof newData[ key ] === 'string' && newData[ key ].length > 0 ) {
					const bookTitle = mw.Title.newFromText( newData[ key ], bs.ns.NS_BOOK );
					if ( bookTitle ) {
						newData[ key ] = bookTitle.getPrefixedText();
					}
				}
			}
			if ( typeof template.params[ key ] === 'string' ) {
				template.params[ key ] = { wt: template.params[ key ] };
			}
			// necessary for checkboxes and templates with yes and no
			if ( typeof template.params[ key ] === 'boolean' ) {
				if ( template.params[ key ] === true ) {
					template.params[ key ] = 'yes';
				} else {
					template.params[ key ] = 'no';
				}
				template.params[ key ] = { wt: template.params[ key ].toString() };
			}

			if ( newData.hasOwnProperty( key ) ) {
				template.params[ key ] = { wt: newData[ key ] };
			}
		}

		mwData.parts[ 0 ].template = template;
		return mwData;
	};

	ext.contentdroplets.registry.register( 'bookpdf', bs.bookshelf.object.BookPDFDroplet );

}( mediaWiki, jQuery, blueSpice ) );
