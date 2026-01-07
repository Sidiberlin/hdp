( function ( mw, $, bs ) {

	bs.util.registerNamespace( 'bs.bookshelf.ui.pages' );

	bs.bookshelf.ui.pages.PDFTemplateMeta = function ( name, config ) {
		config = config || {};
		bs.bookshelf.ui.pages.PDFTemplateMeta.super.call( this, name, config );
		this.$overlay = config.$overlay || true;
	};

	OO.inheritClass( bs.bookshelf.ui.pages.PDFTemplateMeta, bs.bookshelf.ui.pages.MetaDataPage );

	bs.bookshelf.ui.pages.PDFTemplateMeta.prototype.getOutlineLabel = function () {
		return mw.message( 'bs-bookshelfui-bookmetatag-pdftemplate' ).text();
	};

	bs.bookshelf.ui.pages.PDFTemplateMeta.prototype.setup = function () {
		const pdfTemplates = require( './pdftemplates.json' );

		const allTemplates = pdfTemplates.templates;
		const options = [];
		if ( allTemplates.length > 0 ) {
			allTemplates.forEach( ( template ) => {
				const item = new OO.ui.MenuOptionWidget( {
					data: template,
					label: template
				} );
				options.push( item );
			} );
		}

		this.dropdownWidget = new OO.ui.DropdownWidget( {
			menu: {
				items: options
			},
			label: 'Select template',
			$overlay: this.$overlay,
			value: this.value
		} );

		if ( this.value.length > 0 ) {
			this.setValue( this.value );
		}

		const fieldLayout = new OO.ui.FieldLayout( this.dropdownWidget, {
			align: 'top',
			label: mw.message( 'bs-bookshelfui-bookmetatag-pdftemplate' ).text()
		} );

		this.$element.append( fieldLayout.$element );
	};

	bs.bookshelf.ui.pages.PDFTemplateMeta.prototype.getValue = function () {
		const selected = this.dropdownWidget.getMenu().findSelectedItem();
		if ( !selected ) {
			return '';
		}
		return selected.getData();
	};

	bs.bookshelf.ui.pages.PDFTemplateMeta.prototype.setValue = function ( value ) {
		this.dropdownWidget.getMenu().selectItemByData( value );
	};

}( mediaWiki, jQuery, blueSpice ) );
