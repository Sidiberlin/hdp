/* eslint-disable no-underscore-dangle */
window.contentProvisioning = {
	api: {},
	store: {},
	ui: {
		panel: {},
		dialog: {}
	},
	_internal: {
		_api: {
			promise: null,
			api: null
		},
		_getApi: function () {
			// Get API Singleton
			if ( contentProvisioning._internal._api.promise ) {
				return contentProvisioning._internal._api.promise;
			}

			const dfd = $.Deferred();
			if ( !contentProvisioning._internal._api.api ) {
				mw.loader.using( [ 'ext.contentProvisioning.api' ], () => {
					contentProvisioning._internal._api.api = new contentProvisioning.api.Api();
					contentProvisioning._internal._api.promise = null;
					dfd.resolve( contentProvisioning._internal._api.api );
				} );
				contentProvisioning._internal._api.promise = dfd.promise();
				return contentProvisioning._internal._api.promise;
			} else {
				dfd.resolve( contentProvisioning._internal._api.api );
			}
			return dfd.promise();
		}
	}

};
