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

}( mediaWiki, jQuery ) );
