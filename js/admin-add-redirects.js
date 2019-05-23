/**
 * Provides helpful preview of what redirect URLs will look like.
 */
function update_redirect_preview( redirectFieldId, previewHolderId ) {
	let redirectField = document.getElementById( redirectFieldId );

	redirectField.onkeyup = function() {
		let prefix  = '';
		let siteUrl = wpcomLegacyRedirector.siteurl;

		// If it just contains an integer, we assume it is a Post ID.
		if ( redirectField.value.match( /^\d+$/ ) ) {
			prefix = '?p=';
		}

		// If it starts with `http`, then we assume it is an absolute URL.
		if ( redirectField.value.match( /^http.+/ ) ) {
			prefix  = '';
			siteUrl = '';
		}

		document.getElementById( previewHolderId ).textContent = siteUrl + prefix + redirectField.value;
	}
}

update_redirect_preview( 'redirect_from', 'redirect_from_preview' );
update_redirect_preview( 'redirect_to', 'redirect_to_preview' );
