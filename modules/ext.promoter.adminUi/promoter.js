/**
 * Promoter Administrative UI - Common Functions
 */
( function () {
	'use strict';
	mw.promoter = mw.promoter || {};
	mw.promoter.adminUi = {};

	// Collapse and uncollapse detailed view for an individual log entry
	window.toggleLogDisplay = function ( logId ) {
		var thisCollapsed = document.getElementById( 'pr-collapsed-' + logId ),
			thisUncollapsed = document.getElementById( 'pr-uncollapsed-' + logId ),
			thisDetails = document.getElementById( 'pr-log-details-' + logId );
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
		var thisCollapsed = document.getElementById( 'pr-collapsed-filter-arrow' ),
			thisUncollapsed = document.getElementById( 'pr-uncollapsed-filter-arrow' ),
			thisFilters = document.getElementById( 'pr-log-filters' );
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

}() );
