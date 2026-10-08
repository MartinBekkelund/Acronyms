/**
 * Front-end JavaScript for the Acronyms plugin.
 *
 * Provides a touch-friendly tooltip for <abbr> elements on mobile devices.
 * On touch devices, tapping an <abbr> shows a tooltip with the full meaning.
 * Tapping elsewhere or tapping the same element again dismisses the tooltip.
 *
 * @package Acronyms
 */

( function () {
	'use strict';

	var isTouchDevice = ( 'ontouchstart' in window ) ||
		( window.matchMedia && window.matchMedia( '(pointer: coarse)' ).matches );

	if ( ! isTouchDevice ) {
		return;
	}

	var tooltip = null;
	var activeAbbr = null;

	function createTooltip() {
		var el = document.createElement( 'div' );
		el.className = 'acronyms-tooltip';
		el.setAttribute( 'role', 'tooltip' );
		el.style.cssText = 'position:absolute;z-index:999999;background:#333;color:#fff;' +
			'padding:6px 10px;border-radius:4px;font-size:14px;line-height:1.4;' +
			'max-width:280px;box-shadow:0 2px 8px rgba(0,0,0,0.25);' +
			'pointer-events:none;opacity:0;transition:opacity 0.15s ease;';
		document.body.appendChild( el );
		return el;
	}

	function showTooltip( abbrEl ) {
		var title = abbrEl.getAttribute( 'title' );

		if ( ! title ) {
			return;
		}

		if ( ! tooltip ) {
			tooltip = createTooltip();
		}

		tooltip.textContent = title;
		tooltip.style.opacity = '0';
		tooltip.style.display = 'block';

		var rect = abbrEl.getBoundingClientRect();
		var tooltipRect;

		// Position above the element by default.
		tooltip.style.left = '0';
		tooltip.style.top = '0';
		tooltipRect = tooltip.getBoundingClientRect();

		var left = rect.left + ( rect.width / 2 ) - ( tooltipRect.width / 2 ) + window.scrollX;
		var top = rect.top - tooltipRect.height - 8 + window.scrollY;

		// If tooltip would go above viewport, position below instead.
		if ( top - window.scrollY < 0 ) {
			top = rect.bottom + 8 + window.scrollY;
		}

		// Keep tooltip within horizontal bounds.
		if ( left < 4 ) {
			left = 4;
		} else if ( left + tooltipRect.width > document.documentElement.clientWidth - 4 ) {
			left = document.documentElement.clientWidth - tooltipRect.width - 4;
		}

		tooltip.style.left = left + 'px';
		tooltip.style.top = top + 'px';
		tooltip.style.opacity = '1';

		// Temporarily remove the title to prevent the native tooltip.
		abbrEl.setAttribute( 'data-acronyms-title', title );
		abbrEl.removeAttribute( 'title' );

		activeAbbr = abbrEl;
	}

	function hideTooltip() {
		if ( tooltip ) {
			tooltip.style.opacity = '0';
			tooltip.style.display = 'none';
		}

		if ( activeAbbr ) {
			var title = activeAbbr.getAttribute( 'data-acronyms-title' );
			if ( title ) {
				activeAbbr.setAttribute( 'title', title );
				activeAbbr.removeAttribute( 'data-acronyms-title' );
			}
			activeAbbr = null;
		}
	}

	// Event delegation on document body.
	document.body.addEventListener( 'touchstart', function ( event ) {
		var abbrEl = event.target.closest( 'abbr[title], abbr[data-acronyms-title]' );

		if ( abbrEl ) {
			event.preventDefault();

			// Toggle: if tapping the same abbr, hide the tooltip.
			if ( abbrEl === activeAbbr ) {
				hideTooltip();
			} else {
				hideTooltip();
				showTooltip( abbrEl );
			}
		} else {
			hideTooltip();
		}
	}, { passive: false } );
} )();
