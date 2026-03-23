/**
 * Backing JS for Special:Promoter, the campaign list view form.
 *
 * @file
 */
( function () {
	const checkbox = document.getElementById( 'promoter-showarchived' );
	if ( checkbox ) {
		checkbox.addEventListener( 'click', function () {
			// Query DOM on each click to handle any dynamic changes
			const archivedItems = document.querySelectorAll( '.pr-archived-item' );
			const visible = this.checked;
			archivedItems.forEach( ( item ) => {
				item.style.display = visible ? '' : 'none';
			} );
		} );
	}
}() );
