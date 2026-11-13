/**
 * [naws_month_review]: pages between months without a reload.
 *
 * The arrows are ordinary links, so the block works without this script.
 * Here a click fetches the other month, swaps the whole block in and keeps
 * the address in step. Anything that goes wrong - a failed request, an
 * answer without HTML - hands the click back to the link.
 */
( function () {
    'use strict';

    var busy = false;

    function monthOf( link ) {
        try {
            return new URL( link.href, window.location.href ).searchParams.get( 'naws_month' ) || '';
        } catch ( e ) {
            return '';
        }
    }

    function wire( section ) {
        var links = section.querySelectorAll( '.naws-mr-step[href]' );
        Array.prototype.forEach.call( links, function ( link ) {
            link.addEventListener( 'click', function ( event ) {
                // Let the browser keep its own meaning for new-tab clicks.
                if ( event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ) {
                    return;
                }
                event.preventDefault();
                go( section, link );
            } );
        } );
    }

    function go( section, link ) {
        var month = monthOf( link );
        if ( busy ) {
            return;
        }
        if ( ! month || ! window.fetch || ! section.dataset.ajax ) {
            window.location.href = link.href;
            return;
        }

        busy = true;
        section.setAttribute( 'aria-busy', 'true' );

        var body = new URLSearchParams( {
            action: 'naws_get_month_review',
            nonce: section.dataset.nonce || '',
            month: month,
            years: section.dataset.years || '',
            title: section.dataset.title || ''
        } );

        fetch( section.dataset.ajax, { method: 'POST', body: body, credentials: 'same-origin' } )
            .then( function ( response ) {
                return response.ok ? response.json() : null;
            } )
            .then( function ( json ) {
                if ( ! json || ! json.success || ! json.data || typeof json.data.html !== 'string' ) {
                    throw new Error( 'bad answer' );
                }
                swap( section, json.data.html, month );
            } )
            .catch( function () {
                window.location.href = link.href;
            } )
            .then( function () {
                busy = false;
            } );
    }

    /*
     * The server renders the arrows during an admin-ajax call, so their
     * hrefs point at admin-ajax.php. Point them back at this page, keeping
     * its other parameters and the hash, so the fallback and new-tab clicks
     * land on a real page.
     */
    function rebase( section ) {
        var links = section.querySelectorAll( '.naws-mr-step[href]' );
        Array.prototype.forEach.call( links, function ( link ) {
            var month = monthOf( link );
            if ( ! month ) {
                return;
            }
            var url = new URL( window.location.href );
            url.searchParams.set( 'naws_month', month );
            link.href = url.toString();
        } );
    }

    function swap( section, html, month ) {
        var holder = document.createElement( 'div' );
        holder.innerHTML = html;
        var fresh = holder.querySelector( '[data-naws-mr]' );
        if ( ! fresh ) {
            throw new Error( 'no block' );
        }
        rebase( fresh );
        section.replaceWith( fresh );
        wire( fresh );

        var head = fresh.querySelector( '.naws-mr-head' );
        if ( head ) {
            head.focus();
        }

        try {
            var url = new URL( window.location.href );
            url.searchParams.set( 'naws_month', month );
            window.history.replaceState( null, '', url.toString() );
        } catch ( e ) {
            // The address is a courtesy; the block is already swapped.
        }
    }

    function init() {
        Array.prototype.forEach.call( document.querySelectorAll( '[data-naws-mr]' ), wire );
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }
}() );
