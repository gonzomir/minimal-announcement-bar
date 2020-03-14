(function() {
	document.addEventListener( 'DOMContentLoaded', function() {
		var closeButton = document.querySelector( '#announcements button.close' );
		if ( closeButton ) {
			closeButton.addEventListener( 'click', function() {
				this.parentNode.parentNode.setAttribute( 'hidden', 'hidden' );
				var expires_date = new Date();
				expires_date.setMonth( expires_date.getMonth() + 1 );
				document.cookie = this.dataset.cookieName + '=true;path=/;expires=' + expires_date.toUTCString() + ';';
			} );
		}
	} );
} )();
