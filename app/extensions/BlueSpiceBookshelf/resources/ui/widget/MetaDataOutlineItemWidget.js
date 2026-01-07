bs.util.registerNamespace( 'ext.bookshelf.ui.widget' );

ext.bookshelf.ui.widget.MetaDataOutlineItemWidget = function ( config ) {
	const label = config.label || '';
	this.key = config.key || '';
	this.checkboxChange = false;
	this.checkbox = new OO.ui.CheckboxInputWidget( {
		title: mw.message( 'bs-bookshelf-metadata-widget-checkbox-title', label ).text(),
		selected: config.active || false,
		tabIndex: -1
	} );
	this.checkbox.connect( this, {
		change: function ( state ) {
			if ( this.checkboxChange ) {
				this.checkboxChange = false;
				this.emit( 'property_selection', this.key, state );
			}
		}
	} );

	ext.bookshelf.ui.widget.MetaDataOutlineItemWidget.super.call( this, $.extend( config, { // eslint-disable-line no-jquery/no-extend
		classes: [ 'bs-metadata-outline-widget' ],
		$label: $( '<label>' ),
		align: 'inline'
	} ) );

	this.$element.append( this.checkbox.$element, this.$label );
	this.$element.on( 'click', ( e ) => {
		if ( e.target.nodeName === 'INPUT' ) {
			this.checkboxChange = true;
			return;
		}
		const state = this.checkbox.isSelected();
		this.checkbox.setSelected( !state );
		this.emit( 'property_selection', this.key, state );
	} );
};

OO.inheritClass( ext.bookshelf.ui.widget.MetaDataOutlineItemWidget, OO.ui.OptionWidget );
