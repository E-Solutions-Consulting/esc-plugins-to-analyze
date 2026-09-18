(function () {
    function initModal() {
        var modal = document.getElementById( 'ah-terms-consent-modal' );

        if ( ! modal || typeof ahTermsConsent === 'undefined' ) {
            return;
        }

        var accept = modal.querySelector( '.ah-terms-consent-modal__accept' );
        var error  = modal.querySelector( '.ah-terms-consent-modal__error' );
        var close  = modal.querySelector( '.ah-terms-consent-modal__close' );

        document.body.classList.add( 'ah-terms-consent-open' );

        function dismiss() {
            document.body.classList.remove( 'ah-terms-consent-open' );
            modal.style.opacity = '0';
            setTimeout( function () {
                modal.remove();
            }, 150 );
        }

        // Admin-only escape hatch: closes without recording acceptance.
        if ( close ) {
            close.addEventListener( 'click', dismiss );
        }

        accept.addEventListener( 'click', function () {
            accept.disabled = true;
            error.style.display = 'none';

            var body = new URLSearchParams();
            body.set( 'action', ahTermsConsent.action );
            body.set( 'nonce', accept.getAttribute( 'data-nonce' ) );

            fetch( ahTermsConsent.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            } )
                .then( function ( response ) { return response.json(); } )
                .then( function ( data ) {
                    if ( data && data.success ) {
                        dismiss();
                    } else {
                        error.textContent = 'Something went wrong. Please try again.';
                        error.style.display = 'block';
                        accept.disabled = false;
                    }
                } )
                .catch( function () {
                    error.textContent = 'Something went wrong. Please try again.';
                    error.style.display = 'block';
                    accept.disabled = false;
                } );
        } );
    }

    function initBanner() {
        var banner = document.querySelector( '.ah-terms-consent-banner' );

        if ( ! banner ) {
            return;
        }

        var close = banner.querySelector( '.ah-terms-consent-banner__close' );

        if ( close ) {
            close.addEventListener( 'click', function () {
                banner.remove();
            } );
        }
    }

    function init() {
        initModal();
        initBanner();
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }
})();
