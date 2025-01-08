ext = ext || {};
ext.chatbot = ext.chatbot || {};
ext.chatbot.attentionMonitor = {
	config: {
		clickMode: 'pageLoad', // or `anyClick`
		clicksLimit: 20,
		clicksTimeout: 300000, // 5 minutes
		searchResultsThreshold: 5,
	},
	trackingPaused: false,
	$chatButton: null,
	clickTracker: {
		trackClick: function() {
			if ( !ext.chatbot.attentionMonitor.shouldTrack() ) {
				return;
			}
			var now = new Date().getTime(),
				value = ext.chatbot.attentionMonitor.clickTracker.getTrackerCookieValue();
			value = ext.chatbot.attentionMonitor.clickTracker.deleteOldClicks( value, now );
			value.clicks = value.clicks || [];
			value.clicks.push( new Date().getTime() );
			mw.cookie.set( 'chatbot_attention_tracker', JSON.stringify( value ) );
			ext.chatbot.attentionMonitor.checkShouldSeek();
		},
		getTrackerCookieValue: function() {
			var value = mw.cookie.get( 'chatbot_attention_tracker' );
			if ( !value ) {
				return {};
			}
			return JSON.parse( value );
		},
		deleteOldClicks: function( value, now ) {
			now = now || new Date().getTime();
			if ( value.clicks ) {
				value.clicks = value.clicks.filter( function( click ) {
					// Filter out clicks older than timeout amount of minutes
					return now - click < ext.chatbot.attentionMonitor.config.clicksTimeout;
				} );
			}
			return value;
		},
		isLimitReached: function( value ) {
			value = value || ext.chatbot.attentionMonitor.clickTracker.getTrackerCookieValue();
			// Get rid of all clicks older than 5 minutes, so that we can check just recent clicks
			value = ext.chatbot.attentionMonitor.clickTracker.deleteOldClicks( value );
			console.debug( 'ChatBot Attention Monitor: Clicks:', value.clicks && value.clicks.length );
			return value.clicks && value.clicks.length >= ext.chatbot.attentionMonitor.config.clicksLimit;
		},
		clearClicks: function() {
			console.debug( 'ChatBot Attention Monitor: Clearing click count' );
			var value = ext.chatbot.attentionMonitor.clickTracker.getTrackerCookieValue();
			value.clicks = [];
			mw.cookie.set( 'chatbot_attention_tracker', JSON.stringify( value ) );
		}
	},

	triggerAttention: function() {
		if ( ext.chatbot.attentionMonitor.trackingPaused ) {
			return;
		}
		ext.chatbot.attentionMonitor.clickTracker.clearClicks();
		var $bnt = ext.chatbot.attentionMonitor.$chatButton;
		if ( !$bnt.length ) {
			return;
		}
		var isActive = $bnt.hasClass( 'active' );
		$bnt.removeClass( 'active' );
		ext.chatbot.attentionMonitor.stopTracking();
		var i = 0;
		var interval = setInterval( function() {
			$bnt.toggleClass( 'attention' );
			i++;
			if ( i >= 12 ) {
				$bnt.removeClass( 'attention' );
				if ( isActive ) {
					$bnt.addClass( 'active' );
				}
				ext.chatbot.attentionMonitor.resumeTracking();
				clearInterval( interval );
			}
		}, 1000 );
	},
	checkShouldSeek: function() {
		console.debug( 'ChatBot Attention Monitor: Checking if should seek' );
		if (
			ext.chatbot.attentionMonitor.clickTracker.isLimitReached()
		)  {
			ext.chatbot.attentionMonitor.triggerAttention();
		}
	},
	stopTracking: function() {
		console.debug( 'ChatBot Attention Monitor: Pausing tracking' );
		ext.chatbot.attentionMonitor.trackingPaused = true;
	},
	resumeTracking: function() {
		console.debug( 'ChatBot Attention Monitor: Resuming tracking' );
		ext.chatbot.attentionMonitor.trackingPaused = false;
	},
	shouldTrack: function() {
		return !ext.chatbot.attentionMonitor.trackingPaused &&
			ext.chatbot.attentionMonitor.$chatButton.length > 0;
	}
};

var newSession = mw.cookie.get( 'mwuser-sessionId' ) === null;
$( function() {
	ext.chatbot.attentionMonitor.$chatButton = $( '#chat>#maximizeChat' );

	// Tracking user clicks
	if ( ext.chatbot.attentionMonitor.config.clickMode === 'anyClick' ) {
		// Trigger on every click on the page
		$( document ).on( 'click', function() {
			ext.chatbot.attentionMonitor.clickTracker.trackClick();
		} );
	}
	if ( ext.chatbot.attentionMonitor.config.clickMode === 'pageLoad' ) {
		// Trigger on every page load (user clicking different pages), timer to let things load
		setTimeout( function() {
			ext.chatbot.attentionMonitor.clickTracker.trackClick();
		}, 1000 );
	}

	// User log in
	if ( mw.config.get( 'wgChatBotUserLoggedIn' ) ) {
		ext.chatbot.attentionMonitor.clickTracker.clearClicks();
		ext.chatbot.attentionMonitor.triggerAttention();
	}

	setTimeout( function() {
		// not a perfect way to detect new session
		if( newSession && mw.cookie.get( 'mwuser-sessionId' ) ) {
			ext.chatbot.attentionMonitor.clickTracker.clearClicks();
			ext.chatbot.attentionMonitor.triggerAttention();
		}
	}, 500 );

	// ExtendedSearch results > 5
	// -- Autocomplete
	$( document ).on( 'BSExtendedSearchAutocompleteSuggestionsRetrieved', function( e, data ) {
		var resCount = 0;
		for ( var i = 0; i < data.length; i++ ) {
			if ( data[i].rank && data[i].rank !== 'primary' ) {
				continue;
			}
			resCount++;
		}
		if ( resCount > ext.chatbot.attentionMonitor.config.searchResultsThreshold ) {
			ext.chatbot.attentionMonitor.triggerAttention();
		}
	} );
	// -- Fulltext
	var $resPanel = $( '#bs-es-results' );
	if ( $resPanel.length ) {
		$resPanel.on( 'resultsReady', function( e, panel ) {
			if ( panel && panel.results && panel.results.length > ext.chatbot.attentionMonitor.config.searchResultsThreshold ) {
				ext.chatbot.attentionMonitor.triggerAttention();
			}
		} );
	}

	// Click on chatButton
	if ( ext.chatbot.attentionMonitor.$chatButton.length ) {
		ext.chatbot.attentionMonitor.$chatButton.on( 'click', function() {
			ext.chatbot.attentionMonitor.clickTracker.clearClicks();
		} );
	}

	// React to chat events
	window.addEventListener( 'openChat', function() {
		ext.chatbot.attentionMonitor.clickTracker.clearClicks();
		ext.chatbot.attentionMonitor.stopTracking();
	} );
	window.addEventListener( 'minimizeChat', function() {
		ext.chatbot.attentionMonitor.resumeTracking();
	} );
} );
