/**
 * WP Interlinking — Frontend Click Tracker.
 *
 * Lightweight script that tracks clicks on auto-generated interlinks.
 * Uses Navigator.sendBeacon() for non-blocking, fire-and-forget logging.
 *
 * @since 3.0.0
 */
(function() {
	'use strict';

	if ( typeof fppTracker === 'undefined' ) {
		return;
	}

	document.addEventListener( 'click', function( e ) {
		// Find the closest plugin-generated link (identified by data attribute).
		var link = e.target.closest( 'a[data-fpp-keyword-id]' );
		if ( ! link ) {
			return;
		}

		var keywordId = link.getAttribute( 'data-fpp-keyword-id' );
		var postId    = link.getAttribute( 'data-fpp-post-id' ) || '0';
		var targetUrl = link.getAttribute( 'href' );

		if ( ! keywordId || ! targetUrl ) {
			return;
		}

		// Build form data for sendBeacon.
		var data = new FormData();
		data.append( 'action', 'fpp_interlinking_track_click' );
		data.append( 'nonce', fppTracker.nonce );
		data.append( 'keyword_id', keywordId );
		data.append( 'post_id', postId );
		data.append( 'target_url', targetUrl );

		// Use sendBeacon for non-blocking delivery (preferred).
		if ( navigator.sendBeacon ) {
			navigator.sendBeacon( fppTracker.ajax_url, data );
		} else {
			// Fallback: fire-and-forget XHR.
			var xhr = new XMLHttpRequest();
			xhr.open( 'POST', fppTracker.ajax_url, true );
			xhr.send( data );
		}
	});
})();
