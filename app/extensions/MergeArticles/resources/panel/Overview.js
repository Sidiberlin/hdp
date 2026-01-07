( function ( mw, $ ) {
	mergeArticles.panel.Overview = function mergeArticlesOverview( cfg ) {
		cfg = cfg || {};

		this.$element = $( '<div>' );
		this.$element.addClass( 'merge-articles-overview' );

		this.filters = cfg.filters || {};
		this.filterModules = cfg.filterModules || [];

		this.pages = cfg.pages || {};
		this.selectedTypes = [];
		this.currentlyDisplayed = 0;

		this.makeTypeLayout();

		this.makeFilterLayout().done( ( item ) => {
			this.criteriaLayout = new OO.ui.HorizontalLayout( {
				items: [ this.typeLayout, item ]
			} );
			this.criteriaLayout.$element.addClass( 'merge-articles-criterial-layout' );
			this.$element.prepend( this.criteriaLayout.$element );

			// Select Articles by default
			this.articlesTypeButton.emit( 'click' );
		} );

		this.noPagesMessage = new OO.ui.LabelWidget( {
			label: mw.message( 'mergearticles-no-pages-available' ).text()
		} );
		this.noPagesMessage.$element.addClass( 'ma-no-pages-available' );
		this.pageLayout = new OO.ui.HorizontalLayout( {
			items: [ this.noPagesMessage ]
		} );
		this.pageLayout.$element.addClass( 'merge-articles-page-layout' );

		this.$element.append( this.pageLayout.$element );
	};

	OO.initClass( mergeArticles.panel.Overview );

	mergeArticles.panel.Overview.prototype.makeTypeLayout = function () {
		this.articlesTypeButton = new OO.ui.ToggleButtonWidget( {
			label: mw.message( 'mergearticles-type-article' ).text()
		} );
		this.articlesTypeButton.on( 'click', () => {
			this.onTypeChange( 'article', this.articlesTypeButton.getValue() );
		} );
		this.categoriesTypeButton = new OO.ui.ToggleButtonWidget( {
			label: mw.message( 'mergearticles-type-category' ).text()
		} );
		this.categoriesTypeButton.on( 'click', () => {
			this.onTypeChange( 'category', this.categoriesTypeButton.getValue() );
		} );
		this.templatesTypeButton = new OO.ui.ToggleButtonWidget( {
			label: mw.message( 'mergearticles-type-template' ).text()
		} );
		this.templatesTypeButton.on( 'click', () => {
			this.onTypeChange( 'template', this.templatesTypeButton.getValue() );
		} );
		this.filesTypeButton = new OO.ui.ToggleButtonWidget( {
			label: mw.message( 'mergearticles-type-file' ).text()
		} );
		this.filesTypeButton.on( 'click', () => {
			this.onTypeChange( 'file', this.filesTypeButton.getValue() );
		} );

		this.typePicker = new OO.ui.ButtonGroupWidget( {
			items: [
				this.articlesTypeButton,
				this.categoriesTypeButton,
				this.templatesTypeButton,
				this.filesTypeButton
			]
		} );

		this.typeLayout = new OO.ui.FieldsetLayout( {
			id: 'merge-articles-type-layout',
			label: 'Type',
			align: 'top',
			items: [
				this.typePicker
			]
		} );
	};

	mergeArticles.panel.Overview.prototype.makeFilterLayout = function () {
		const dfd = $.Deferred();
		mw.loader.using( [ 'ext.mergearticles.filters' ].concat( this.filterModules ), () => {
			const instances = {}, layouts = [];
			const keys = Object.keys( this.filters );
			for ( let i = 0; i < keys.length; i++ ) {
				const filter = this.filters[ keys[ i ] ];
				if ( !filter.hasOwnProperty( 'id' ) ) {
					continue;
				}
				const widgetClass = this.stringToCallback( filter.widgetClass ),
					widget = new widgetClass( Object.assign( {}, true, { // eslint-disable-line new-cap
						id: filter.id
					}, filter.widgetData || {} ) ),
					layout = new OO.ui.FieldLayout( widget.getWidget(), {
						align: 'top',
						label: filter.displayName
					} );
				widget.connect( this, { change: 'onFilter' } );
				layouts.push( layout );
				instances[ keys[ i ] ] = widget;
			}

			this.filters = instances;

			dfd.resolve( new OO.ui.HorizontalLayout( {
				items: layouts,
				classes: [ 'ma-filter-layout' ]
			} ) );
		} );

		return dfd.promise();
	};

	mergeArticles.panel.Overview.prototype.onTypeChange = function ( type, value ) {
		if ( value ) {
			this.selectedTypes.push( type );
		} else {
			const idx = this.selectedTypes.indexOf( type );
			this.selectedTypes.splice( idx, 1 );
		}

		this.updatePages();
	};

	mergeArticles.panel.Overview.prototype.updatePages = function () {
		this.currentlyDisplayed = 0;
		this.pageLayout.$element
			.children( '.ma-page-item' ).remove();

		const pages = [];
		for ( const idx in this.selectedTypes ) {
			const type = this.selectedTypes[ idx ];
			Array.prototype.push.apply( pages, this.applyFilter( this.pages[ type ] ) );
		}

		this.addPages( pages );
		this.checkPagesShown();
	};

	mergeArticles.panel.Overview.prototype.addPages = function ( pages ) {
		for ( const idx in pages ) {
			const page = pages[ idx ];
			const pageItemWidget = new mergeArticles.ui.PageItemWidget( page );
			pageItemWidget.on( 'actionClick', this.onPageAction.bind( this ) );

			this.pageLayout.$element.append( pageItemWidget.$element );
			this.currentlyDisplayed++;
		}
	};

	mergeArticles.panel.Overview.prototype.onPageAction = function ( data ) {
		const params = {
			originID: data.origin.id,
			targetText: data.target.text
		};
		if ( data.action === 'compare' ) {
			params.targetID = data.target.id;
		}
		window.location.href = mw.util.getUrl(
			'Special:MergeArticles/' + data.action, params
		);
	};

	mergeArticles.panel.Overview.prototype.onFilter = function () {
		this.updatePages();
	};

	mergeArticles.panel.Overview.prototype.applyFilter = function ( pages ) {
		let filteredPages = pages;
		for ( const name in this.filters ) {
			if ( !this.filters.hasOwnProperty( name ) ) {
				continue;
			}
			filteredPages = this.filters[ name ].filter( filteredPages );
		}

		return filteredPages;
	};

	mergeArticles.panel.Overview.prototype.showNoPages = function () {
		this.noPagesMessage.$element.show();
	};

	mergeArticles.panel.Overview.prototype.hideNoPages = function () {
		this.noPagesMessage.$element.hide();
	};

	mergeArticles.panel.Overview.prototype.checkPagesShown = function () {
		if ( this.currentlyDisplayed === 0 ) {
			this.showNoPages();
		} else {
			this.hideNoPages();
		}
	};

	mergeArticles.panel.Overview.prototype.stringToCallback = function ( cls ) {
		const parts = cls.split( '.' );
		let func = window[ parts[ 0 ] ];
		for ( let i = 1; i < parts.length; i++ ) {
			func = func[ parts[ i ] ];
		}

		return func;
	};
}( mediaWiki, jQuery ) );
