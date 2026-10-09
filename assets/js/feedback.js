/**
 * Ruvelo Feedback: progressive enhancement for the board, request pages,
 * the submit form and the What's new bell. Everything works without it;
 * this makes votes, similar requests and "seen" happen in place.
 */
( function () {
	'use strict';

	var config = window.ruveloFeedback || {};
	var __ = window.wp && window.wp.i18n ? window.wp.i18n.__ : function ( text ) { return text; };
	var sprintf = window.wp && window.wp.i18n ? window.wp.i18n.sprintf : function ( text ) { return text; };
	var _n = window.wp && window.wp.i18n ? window.wp.i18n._n : function ( one, many, n ) { return n === 1 ? one : many; };

	function api( path, options ) {
		options = options || {};
		var headers = { Accept: 'application/json' };
		if ( config.nonce ) {
			headers[ 'X-WP-Nonce' ] = config.nonce;
		}
		if ( options.body ) {
			headers[ 'Content-Type' ] = 'application/json';
		}
		return fetch( config.rest + path, {
			method: options.method || 'GET',
			credentials: 'same-origin',
			headers: headers,
			body: options.body ? JSON.stringify( options.body ) : undefined,
		} ).then( function ( response ) {
			return response.json().then( function ( data ) {
				if ( ! response.ok ) {
					var error = new Error( ( data && data.message ) || response.statusText );
					error.status = response.status;
					throw error;
				}
				return data;
			} );
		} );
	}

	function announce( root, message ) {
		var live = ( root && root.closest( '.ruvelo-fb' ) || document ).querySelector( '[data-ruvelo-live]' );
		if ( live ) {
			live.textContent = '';
			window.setTimeout( function () { live.textContent = message; }, 50 );
		}
	}

	function formatNumber( n ) {
		try {
			return new Intl.NumberFormat( document.documentElement.lang || undefined ).format( n );
		} catch ( e ) {
			return String( n );
		}
	}

	// Votes --------------------------------------------------------------

	document.addEventListener( 'submit', function ( event ) {
		var form = event.target.closest && event.target.closest( 'form[data-ruvelo-vote]' );
		if ( ! form || ! config.rest || ( ! config.nonce && config.signedIn ) ) {
			return;
		}
		if ( ! config.signedIn ) {
			return; // Guests post the form normally (their vote is tied to a signed cookie).
		}
		event.preventDefault();
		var id = form.getAttribute( 'data-ruvelo-vote' );
		var input = form.querySelector( 'input[name=vote]' );
		var button = form.querySelector( 'button' );
		var adding = input.value === '1';
		var title = ( form.closest( '.ruvelo-fb-card, .ruvelo-fb-request-head' ) || document ).querySelector( '.ruvelo-fb-card-title a, h1' );
		button.disabled = true;

		api( 'requests/' + id + '/vote', { method: adding ? 'POST' : 'DELETE' } ).then( function ( data ) {
			document.querySelectorAll( 'form[data-ruvelo-vote="' + id + '"]' ).forEach( function ( other ) {
				var otherButton = other.querySelector( 'button' );
				var count = other.querySelector( '[data-ruvelo-count]' );
				other.querySelector( 'input[name=vote]' ).value = data.voted ? '0' : '1';
				otherButton.setAttribute( 'aria-pressed', data.voted ? 'true' : 'false' );
				if ( count ) {
					count.textContent = formatNumber( data.votes );
				}
				var votesText = sprintf( _n( '%s vote', '%s votes', data.votes, 'ruvelo-feedback' ), formatNumber( data.votes ) );
				var name = title ? title.textContent.trim() : '';
				/* translators: 1: request title, 2: "12 votes" */
				otherButton.setAttribute( 'aria-label', sprintf( data.voted ? __( 'Remove your vote for “%1$s”, %2$s', 'ruvelo-feedback' ) : __( 'Vote for “%1$s”, %2$s', 'ruvelo-feedback' ), name, votesText ) );
			} );
			announce( form, data.voted ? __( 'Your vote is in.', 'ruvelo-feedback' ) : __( 'Your vote was taken back.', 'ruvelo-feedback' ) );
		} ).catch( function ( error ) {
			if ( error.status === 403 && error.message ) {
				announce( form, error.message );
			} else {
				form.submit(); // Fall back to the normal post.
			}
		} ).finally( function () {
			button.disabled = false;
		} );
	} );

	// Similar requests while typing a title -------------------------------

	document.querySelectorAll( 'form[data-ruvelo-submit]' ).forEach( function ( form ) {
		var js = form.querySelector( '[data-ruvelo-js]' );
		if ( js ) {
			js.value = '1';
		}
		var title = form.querySelector( '[data-ruvelo-title]' );
		var region = form.querySelector( '[data-ruvelo-similar]' );
		if ( ! title || ! region || ! config.rest ) {
			return;
		}
		var timer = null;
		var last = '';

		function render( items ) {
			region.textContent = '';
			if ( ! items.length ) {
				return;
			}
			var box = document.createElement( 'div' );
			box.className = 'ruvelo-fb-similar';
			var heading = document.createElement( 'h2' );
			heading.textContent = __( 'Is it one of these? Vote for it instead.', 'ruvelo-feedback' );
			box.appendChild( heading );
			var list = document.createElement( 'ul' );
			items.forEach( function ( item ) {
				var li = document.createElement( 'li' );
				var a = document.createElement( 'a' );
				a.href = item.url;
				var votes = document.createElement( 'span' );
				votes.className = 'ruvelo-fb-similar-votes';
				votes.textContent = formatNumber( item.votes );
				var text = document.createElement( 'span' );
				text.className = 'ruvelo-fb-similar-title';
				text.textContent = item.title;
				a.appendChild( votes );
				a.appendChild( text );
				if ( item.status_label ) {
					var chip = document.createElement( 'span' );
					chip.className = 'ruvelo-fb-chip ruvelo-fb-chip--quiet';
					chip.textContent = item.status_label;
					a.appendChild( chip );
				}
				li.appendChild( a );
				list.appendChild( li );
			} );
			box.appendChild( list );
			region.appendChild( box );
		}

		title.addEventListener( 'input', function () {
			window.clearTimeout( timer );
			timer = window.setTimeout( function () {
				var q = title.value.trim();
				if ( q === last ) {
					return;
				}
				last = q;
				if ( q.length < 3 ) {
					render( [] );
					return;
				}
				api( 'requests/similar?q=' + encodeURIComponent( q ) ).then( function ( data ) {
					if ( title.value.trim() === q ) {
						render( data );
					}
				} ).catch( function () {} );
			}, 250 );
		} );
	} );

	// What's new ----------------------------------------------------------

	document.querySelectorAll( '[data-ruvelo-whats-new]' ).forEach( function ( details ) {
		var badge = details.querySelector( '[data-ruvelo-badge]' );
		var summary = details.querySelector( 'summary' );
		summary.setAttribute( 'aria-expanded', 'false' );

		details.addEventListener( 'toggle', function () {
			summary.setAttribute( 'aria-expanded', details.open ? 'true' : 'false' );
			if ( details.open && badge && ! badge.hidden ) {
				badge.hidden = true;
				if ( config.rest ) {
					api( 'changelog/seen', { method: 'POST' } ).catch( function () {} );
				}
			}
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( details.open && ! details.contains( event.target ) ) {
				details.open = false;
			}
		} );
		details.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'Escape' && details.open ) {
				details.open = false;
				summary.focus();
			}
		} );
	} );
}() );
