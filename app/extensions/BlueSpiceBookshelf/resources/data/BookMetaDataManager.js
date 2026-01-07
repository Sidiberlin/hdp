bs.util.registerNamespace( 'ext.bookshelf.data' );

ext.bookshelf.data.BookMetaDataManager = function ( bookTitle ) {
	this.loaded = false;
	this.data = [];
	this.bookTitle = bookTitle;
};

OO.initClass( ext.bookshelf.data.BookMetaDataManager );

ext.bookshelf.data.BookMetaDataManager.prototype.load = function () {
	const dfd = $.Deferred();
	mw.loader.using( [ 'bluespice.bookshelf.api' ] ).done( () => {
		const api = new ext.bookshelf.api.Api();
		api.getBookMetadata( this.bookTitle ).done( ( data ) => {
			this.data = data;
			this.loaded = true;
			dfd.resolve( this.data );
		} ).fail( () => {
			dfd.resolve();
		} );
	} );

	return dfd.promise();
};

ext.bookshelf.data.BookMetaDataManager.prototype.setData = function ( data ) {
	this.data = data;
};

ext.bookshelf.data.BookMetaDataManager.prototype.getData = function () {
	return this.data;
};

ext.bookshelf.data.BookMetaDataManager.prototype.save = function ( data ) {
	this.data = data;
	const dfd = $.Deferred();
	mw.loader.using( [ 'bluespice.bookshelf.api' ] ).done( () => {
		const api = new ext.bookshelf.api.Api();
		api.saveBookMetadata( this.bookTitle, data ).done( () => {
			// TODO: check success
			dfd.resolve();
		} ).fail( () => {
			dfd.resolve();
		} );
	} );

	return dfd.promise();
};
