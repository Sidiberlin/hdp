( function () {
	window.mergeArticles = window.mergeArticles || {};
	window.mergeArticles.widget = window.mergeArticles.widget || {};

	mergeArticles.widget.Filter = function ( cfg ) {
		OO.EventEmitter.call( this );

		this.widget = this.makeWidget( cfg );
		this.widget.connect( this, {
			change: function ( val ) {
				this.emit( 'change', val );
			}
		} );

		this.$element = this.widget.$element;
	};

	OO.initClass( mergeArticles.widget.Filter );
	OO.mixinClass( mergeArticles.widget.Filter, OO.EventEmitter );

	mergeArticles.widget.Filter.prototype.makeWidget = function ( cfg ) { // eslint-disable-line no-unused-vars
		// STUB: to be overriden
	};

	mergeArticles.widget.Filter.prototype.getWidget = function () {
		return this.widget;
	};

	mergeArticles.widget.Filter.prototype.filter = function ( pages ) { // eslint-disable-line no-unused-vars
		// STUB
	};
}() );
