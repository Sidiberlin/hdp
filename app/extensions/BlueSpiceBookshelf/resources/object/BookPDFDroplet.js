( function ( mw, $, bs ) {

	bs.util.registerNamespace( 'bs.bookshelf.object' );

	bs.bookshelf.object.BookPDFDroplet = function( cfg ) {
		bs.bookshelf.object.BookPDFDroplet.parent.call( this, cfg );
	};

	OO.inheritClass( bs.bookshelf.object.BookPDFDroplet, ext.contentdroplets.object.TransclusionDroplet );

	bs.bookshelf.object.BookPDFDroplet.prototype.templateMatches = function( templateData ) {
		if ( !templateData ) {
			return false;
		}
		var target = templateData.target.wt;
		return target.trim( '\n' ) === 'BookPDFLink';
	};

	bs.bookshelf.object.BookPDFDroplet.prototype.toDataElement = function( domElements, converter  ) {
		return false;
	};

	bs.bookshelf.object.BookPDFDroplet.prototype.getFormItems = function() {
		var config = require( './config.json' );
		var templates = [];
		for ( var entry in config.availableTemplates ) {
			var item =  {
				data: encodeURI( config.availableTemplates[ entry ] ),
				label: config.availableTemplates[ entry ]
			};
			templates.push( item );
		}
		var templateSelectDisabled = false;
		if ( templates.length === 1 ) {
			templateSelectDisabled = true;
		}

		return [
			{
				name: 'book',
				label: mw.message( 'bs-bookshelf-droplet-pdf-book-label' ).plain(),
				type: 'title',
				namespace: 1504,
			},
			{
				name: 'template',
				label: mw.message( 'bs-bookshelf-droplet-pdf-template-label' ).plain(),
				type: 'dropdown',
				default: {
					data: encodeURI( config.defaultTemplate ),
					label: config.defaultTemplate
				},
				options: templates,
				disabled: templateSelectDisabled
			},
			{
				name: 'label',
				label: mw.message( 'bs-bookshelf-droplet-pdf-link-label' ).plain(),
				type: 'text'
			}
		];
	};

	ext.contentdroplets.registry.register( 'bookpdf', bs.bookshelf.object.BookPDFDroplet );

} )( mediaWiki, jQuery, blueSpice );
