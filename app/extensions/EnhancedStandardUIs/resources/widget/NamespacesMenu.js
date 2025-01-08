ext = ext || {};
ext.enhancedUI = ext.enhancedUI || {};
ext.enhancedUI.widget = ext.enhancedUI.widget || {};

require( './NamespaceOptionWidget.js' );

ext.enhancedUI.widget.NamespacesMenu = function ( cfg ) {
	cfg = cfg || {};
	ext.enhancedUI.widget.NamespacesMenu.super.call( this, cfg );
	this.selectedNSId = cfg.selectedNSId || 0;
	this.namespaces = require( './namespaceConfig.json' );
	this.namespaces[ 0 ].name = mw.message( 'blanknamespace' ).text();
	this.checkSelectedNS();
	this.setup();

};

OO.inheritClass( ext.enhancedUI.widget.NamespacesMenu, OO.ui.Widget );

ext.enhancedUI.widget.NamespacesMenu.prototype.checkSelectedNS = function () {
	// eslint-disable-next-line no-loop-func
	var namespace = this.namespaces.find( function ( ns ) {
		return ns.id === this.selectedNSId;
	}.bind( this ) );
	this.selectedNSIsContent = namespace.isContent;
	this.selectedNSIsTalk = namespace.isTalk;
};

ext.enhancedUI.widget.NamespacesMenu.prototype.setup = function () {
	this.setupConfigButton();
	this.setupNamespaceMenu();
};

ext.enhancedUI.widget.NamespacesMenu.prototype.setupConfigButton = function () {
	var popupContent = this.getPopupContent();
	this.configPopupButton = new OO.ui.PopupButtonWidget( {
		icon: 'settings',
		framed: false,
		popup: popupContent,
		label: mw.message( 'enhanced-standard-uis-allpages-config' ).text(),
		invisibleLabel: true
	} );
	var fieldlayout = new OO.ui.FieldLayout( this.configPopupButton, {
		align: 'inline',
		label: mw.message( 'enhanced-standard-uis-allpages-config' ).text(),
		classes: [ 'enhanced-ui-allpages-config-header' ]
	} );
	this.$element.append( fieldlayout.$element );
};

ext.enhancedUI.widget.NamespacesMenu.prototype.setupNamespaceMenu = function () {
	this.selectWidget = new OO.ui.OutlineSelectWidget();
	this.selectWidget.$element.attr( 'aria-label',
		mw.message( 'enhanced-standard-uis-allpages-namespace-list-label' ).text() );
	this.selectWidget.connect( this, {
		select: 'namespaceSelection'
	} );
	this.updateNSMenu();

	this.menuPanel = new OO.ui.PanelLayout( {
		expanded: false,
		$content: this.selectWidget.$element
	} );

	this.$element.append( this.menuPanel.$element );
};

ext.enhancedUI.widget.NamespacesMenu.prototype.getPopupContent = function () {
	this.includeTalkNS = new OOJSPlus.ui.widget.CheckboxInputWidget( {
		selected: mw.user.options.get( 'allpages-show-talk' )
	} );
	this.includeTalkNS.connect( this, {
		change: function () {
			this.updatePreference( 'talk', this.includeTalkNS.isSelected() );
			this.updateNSMenu();
		}
	} );
	var includeTalkLayout = new OO.ui.FieldLayout(
		this.includeTalkNS,
		{
			label: mw.message( 'enhanced-standard-uis-allpages-include-talk-ns' ).text(),
			align: 'inline'
		}
	);
	this.includeNonContentNS = new OOJSPlus.ui.widget.CheckboxInputWidget( {
		selected: mw.user.options.get( 'allpages-show-non-content' )
	} );
	this.includeNonContentNS.connect( this, {
		change: function () {
			this.updatePreference( 'non-content', this.includeNonContentNS.isSelected() );
			this.updateNSMenu();
		}
	} );
	var includeNonContentLayout = new OO.ui.FieldLayout(
		this.includeNonContentNS,
		{
			label: mw.message( 'enhanced-standard-uis-allpages-include-non-content-ns' ).text(),
			align: 'inline'
		}
	);
	this.includeRedirect = new OOJSPlus.ui.widget.CheckboxInputWidget( {
		selected: mw.user.options.get( 'allpages-show-redirect' )
	} );
	this.includeRedirect.connect( this, {
		change: 'updateRedirect'
	} );
	var includeRedirectsLayout = new OO.ui.FieldLayout(
		this.includeRedirect,
		{
			label: mw.message( 'enhanced-standard-uis-allpages-include-redirect' ).text(),
			align: 'inline'
		}
	);
	var $content = $( '<div>' );
	$content.append( includeTalkLayout.$element );
	$content.append( includeNonContentLayout.$element );
	$content.append( includeRedirectsLayout.$element );
	return {
		head: true,
		label: mw.message( 'enhanced-standard-uis-allpages-config-ns' ).text(),
		padded: true,
		align: 'forwards',
		autoFlip: false,
		$content: $content
	};
};

ext.enhancedUI.widget.NamespacesMenu.prototype.updateNSMenu = function () {
	var selectedItem = this.selectWidget.findFirstSelectedItem();
	this.selectWidget.clearItems();
	this.nsOptions = [];
	var includeNonContent = this.includeNonContentNS.isSelected();
	if ( !this.selectedNSIsContent ) {
		includeNonContent = true;
	}
	var includeTalk = this.includeTalkNS.isSelected();
	if ( this.selectedNSIsTalk ) {
		includeTalk = true;
	}

	for ( var nsId in this.namespaces ) {
		var namespace = this.namespaces[ nsId ];
		if ( includeTalk && namespace.isTalk ) {
			var namespaceTalkId = namespace.id;
			// eslint-disable-next-line no-loop-func
			var relatedNS = this.namespaces.filter( function ( ns ) {
				return ns.id === ( namespaceTalkId - 1 );
			} );
			if ( !relatedNS ) {
				continue;
			}
			if ( !relatedNS[ 0 ].isContent && !includeNonContent ) {
				continue;
			}
			var option = new ext.enhancedUI.widget.NamespaceOptionWidget( {
				data: namespace.id,
				label: namespace.name,
				count: namespace.pageCount
			} );
			if ( namespaceTalkId === 1 ) {
				this.nsOptions.splice( 1, 0, option );
			} else {
				this.nsOptions.push( option );
			}
			continue;
		}
		if ( ( !includeNonContent && !namespace.isContent ) ||
			( includeNonContent && namespace.isTalk ) ) {
			continue;
		}
		this.nsOptions.push(
			new ext.enhancedUI.widget.NamespaceOptionWidget( {
				data: namespace.id,
				label: namespace.name,
				count: namespace.pageCount
			} )
		);
	}
	this.selectWidget.addItems( this.nsOptions );
	this.selectWidget.selectItemByData( selectedItem ? selectedItem.data : this.selectedNSId );
};

ext.enhancedUI.widget.NamespacesMenu.prototype.getSelectedNamespaceId = function () {
	var nsOption = this.selectWidget.findSelectedItem();
	return nsOption.data;
};

ext.enhancedUI.widget.NamespacesMenu.prototype.namespaceSelection = function () {
	var nsOption = this.selectWidget.findSelectedItem();
	this.emit( 'select', nsOption.data );
};

ext.enhancedUI.widget.NamespacesMenu.prototype.updateRedirect = function () {
	this.updatePreference( 'redirect', this.includeRedirect.isSelected() );
	this.emit( 'redirectChange', this.includeRedirect.isSelected() );
};

ext.enhancedUI.widget.NamespacesMenu.prototype.getRedirectStatus = function () {
	return this.includeRedirect.isSelected();
};

ext.enhancedUI.widget.NamespacesMenu.prototype.updatePreference = function ( preference, value ) {
	var val = '0';
	if ( value ) {
		val = '1';
	}
	if ( !mw.user.isAnon() ) {
		mw.loader.using( 'mediawiki.api' ).done( function () {
			mw.user.options.set( 'allpages-show-' + preference, val );
			new mw.Api().saveOption( 'allpages-show-' + preference, val );
		} );
	}
};
