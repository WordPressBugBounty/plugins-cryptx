/**
 * Makes dismissing the review notice stick.
 *
 * WordPress draws the cross on any notice carrying "is-dismissible", but all
 * it does is remove the element -- the next page load brings it straight back.
 * Everything here is therefore an enhancement of something that already works
 * without it: "No thanks" is a real link to a real handler, and following it
 * records the refusal and returns to the page. This just spares the reload,
 * and gives the cross the same meaning as the link next to it.
 *
 * No build step and no dependencies. The URL and its nonce arrive in a data
 * attribute rather than in an inline script, which is the same reason the
 * front end carries data-cx: a page with a strict Content-Security-Policy
 * should not need an exception on this plugin's account.
 */
( function () {
	var notice = document.getElementById( 'cryptx-review-notice' );

	if ( ! notice ) {
		return;
	}

	var url = notice.getAttribute( 'data-cryptx-review' );

	if ( ! url ) {
		return;
	}

	var recorded = false;

	/**
	 * Tells the server not to ask again.
	 *
	 * Fire and forget: the answer is a redirect to the page we are already on,
	 * and there is nothing useful to do with it. A failure is not worth
	 * reporting either -- the worst case is being asked once more.
	 */
	function record() {
		if ( recorded ) {
			return;
		}

		recorded = true;

		window.fetch( url, {
			credentials: 'same-origin',
			redirect: 'follow',
		} ).catch( function () {} );
	}

	// Delegated, because WordPress appends the cross after this file runs.
	notice.addEventListener( 'click', function ( event ) {
		var target = event.target;

		if ( ! target || ! target.closest ) {
			return;
		}

		if ( target.closest( '.notice-dismiss' ) ) {
			record();

			return;
		}

		var link = target.closest( '[data-cryptx-review-action]' );

		if ( ! link ) {
			return;
		}

		record();

		// The review page opens in its own tab, so this one stays put and the
		// notice has to be taken away by hand. Declining navigates nowhere at
		// all -- the request above is the whole of it.
		if ( link.getAttribute( 'data-cryptx-review-action' ) === 'dismiss' ) {
			event.preventDefault();
		}

		notice.remove();
	} );
} )();
