ext.AIEditingAssistant.ui.PromptBooklet = function ( config ) {
	config = config || {};
	this.operationalText = config.text;
	ext.AIEditingAssistant.ui.PromptBooklet.super.call( this, {
		outlined: false,
		$overlay: config.$overlay,
		expanded: false
	} );
	this.initialize();

	this.connect( this, {
		set: function( page ) {
			if ( page instanceof ext.AIEditingAssistant.ui.CommandExecution ) {
				page.init();
				this.activePage = page;
				this.emit( 'pageSet', page );
			} else {
				this.emit( 'reset' );
			}
		}
	} );
};

OO.inheritClass( ext.AIEditingAssistant.ui.PromptBooklet, OO.ui.BookletLayout );

ext.AIEditingAssistant.ui.PromptBooklet.prototype.getActivePage = function () {
	return this.activePage || null;
};

ext.AIEditingAssistant.ui.PromptBooklet.prototype.initialize = function () {
	var pages = [],
		commands = {};

	for ( var key in ext.AIEditingAssistant.commandRegistry.registry ) {
		if ( !ext.AIEditingAssistant.commandRegistry.registry.hasOwnProperty( key ) ) {
			continue;
		}

		var data = ext.AIEditingAssistant.commandRegistry.registry[ key ];
		var page = new ext.AIEditingAssistant.ui.CommandExecution( {
			label: mw.msg( data.labelMsg ),
			data: $.extend( data, { key: key } )
		}, this.operationalText );
		page.connect( this, {
			loadingChange: function( isLoading, wasSuccessful, isMainCall ) {
				this.emit( 'loadingChange', this.activePage, isLoading, wasSuccessful, isMainCall );
			},
			undo: function() {
				this.emit('undo');
			}
		} );
		commands[key] = mw.msg( data.labelMsg );
		pages.push( page );
	}
	var selectionPage = new ext.AIEditingAssistant.ui.CommandSelectionPage( {}, commands );
	pages = [ selectionPage ].concat( pages );
	this.addPages( pages );

	selectionPage.connect( this, {
		commandSelect: function( key ) {
			this.setPage( 'commandPage_' + key );
		}
	} );
};