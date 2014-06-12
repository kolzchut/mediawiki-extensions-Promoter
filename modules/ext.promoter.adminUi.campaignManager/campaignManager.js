/**
 * Backing JS for Special:Promoter, the campaign list view form.
 *
 * This file is part of the Promoter Extension to MediaWiki
 * https://www.mediawiki.org/wiki/Extension:Promoter
 *
 * @section LICENSE
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */
( function ( $ ) {
	var RAND_MAX = 30,
		step_size = 100 / RAND_MAX;
	$( '#promoter-throttle-amount' ).slider( {
		range: "min",
		min: 0,
		max: 100,
		value: $( "#promoter-throttle-cur" ).val(),
		step: step_size,
		slide: function( event, element ) {
			var val = Number( element.value ),
				rounded = Math.round( val * 10 ) / 10;
			$( "#promoter-throttle-echo" ).html( String( rounded ) + "%" );
			$( "#promoter-throttle-cur" ).val( val );
		}
	} );

	function updateThrottle() {
		if ( $( '#throttle-enabled' ).prop( 'checked' ) ) {
			$( '.pr-throttle-amount' ).show();
		} else {
			$( '.pr-throttle-amount' ).hide();
		}
	}
	$( '#throttle-enabled' ).click( updateThrottle );

	function updateWeightColumn() {
		if ( $( '#balanced' ).prop( 'checked' ) ) {
			$( '.pr-weight' ).hide();
		} else {
			$( '.pr-weight' ).show();
		}
	}
	$( '#balanced' ).click( updateWeightColumn );

	$( '#promoter-showarchived' ).click( function() {
		if ( $( this ).prop( 'checked' ) === true ) {
			$( '.pr-archived-item' ).show();
		} else {
			$( '.pr-archived-item' ).hide();
		}
	});

	updateThrottle();
	updateWeightColumn();
} )( jQuery );
