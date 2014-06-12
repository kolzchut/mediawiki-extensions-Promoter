/**
 * Promoter Administrative UI - Common Functions
 */
/* jshint jquery:true */
/* global mediaWiki */
( function ( mw, $ ) {
	"use strict";
	mw.promoter = {};
	mw.promoter.adminUi = {};

	// Collapse and uncollapse detailed view for an individual log entry
	window.toggleLogDisplay = function ( logId ) {
		var thisCollapsed = document.getElementById( 'pr-collapsed-' + logId );
		var thisUncollapsed = document.getElementById( 'pr-uncollapsed-' + logId );
		var thisDetails = document.getElementById( 'pr-log-details-' + logId );
		if ( thisCollapsed.style.display === 'none' ) {
			thisUncollapsed.style.display = 'none';
			thisCollapsed.style.display = 'block';
			thisDetails.style.display = 'none';
		} else {
			thisCollapsed.style.display = 'none';
			thisUncollapsed.style.display = 'block';
			thisDetails.style.display = 'table-row';
		}
	};

	// Collapse and uncollapse log filter interface
	window.toggleFilterDisplay = function () {
		var thisCollapsed = document.getElementById( 'pr-collapsed-filter-arrow' );
		var thisUncollapsed = document.getElementById( 'pr-uncollapsed-filter-arrow' );
		var thisFilters = document.getElementById( 'pr-log-filters' );
		if ( thisCollapsed.style.display === 'none' ) {
			thisUncollapsed.style.display = 'none';
			thisCollapsed.style.display = 'inline-block';
			thisFilters.style.display = 'none';
		} else {
			thisCollapsed.style.display = 'none';
			thisUncollapsed.style.display = 'inline-block';
			thisFilters.style.display = 'block';
		}
	};

	// Switch among various log displays
	window.switchLogs = function ( baseUrl, logType ) {
		encodeURIComponent( logType );
		window.location = baseUrl + '?log=' + logType;
	};

	window.addEventListener( 'message', receiveMessage, false );
	function receiveMessage( event ) {
		var remoteData = JSON.parse( event.data );
		if ( remoteData.ad && remoteData.height ) {
			$( "#pr-ad-preview-" + remoteData.ad + " iframe" ).height( remoteData.height );
		}
	}

	$(document).ready( function ( $ ) {
		// Render jquery.ui.datepicker on appropriate fields
		$( '.promoter-datepicker' ).each( function () {
			var altFormat = 'yymmdd000000';
			var altField = document.getElementById( this.id + '_timestamp' );
			// Remove the time, leaving only the date info
			$( this ).datepicker({
				'altField': altField,
				'altFormat': altFormat,
				'dateFormat': 'yy-mm-dd'
			});

			if ( altField.value ) {
				altField.value = altField.value.substr( 0, 8 ) + '000000';
				var defaultDate = $.datepicker.parseDate( altFormat, altField.value );
				$( this ).datepicker(
					'setDate', defaultDate
				);
			}
		});
		$( '.promoter-datepicker-limit_one_year' ).datepicker(
			'option',
			{
				'maxDate': '+1Y'
			}
		);

		// Do the fancy multiselector; but we have to wait for some arbitrary time until the
		// CSS has been applied... Yes, this is an egregious hack until I rewrite the mutliselector
		// to NOT suck -- e.g. make it dynamic... whoo...
		setTimeout( function() {
			$('select[multiple="multiple"]' ).multiselect({sortable: false, dividerLocation: 0.5});
		}, 250);

		// Special:Promoter; keep data-sort-value attributes for
		// jquery.tablesorter in sync
		$( '.mw-pr-input-check-sort' ).on( 'change click blur', function () {
			$(this).parent( 'td' )
				.data( 'sortValue', Number( this.checked ) );
		} );

	} );

}( mediaWiki, jQuery ) );
