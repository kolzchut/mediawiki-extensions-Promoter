/* jshint jquery:true */
/* global mediaWiki */
( function ( $, mw ) {
	"use strict";
	$(document).ready(function() {
		$(".promotion-gallery").each( function() {
			var $gallery = $(this);
			var $carousel = $gallery.find( '.owl-carousel' );

			$carousel.owlCarousel({
				items : 3,
				dots: false,
				//nav: true,
				//navContainer: '.gallery-controls',
				rtl: $('#mw-content-text').attr('dir') === 'rtl'
			});

			$gallery.find( '.owl-next' ).click( function( e ) {
				$carousel.trigger("next.owl.carousel");
				e.preventDefault();
			});
			$gallery.find( '.owl-prev' ).click( function( e ) {
				$carousel.trigger("prev.owl.carousel");
				e.preventDefault();
			});

			$carousel.find( '.owl-item' ).equalizeCols();

			$carousel.keydown( function(event) {
				switch( event.key ) {
					case 'Left': $carousel.trigger("next.owl.carousel");
						break;
					case 'Right': $carousel.trigger("prev.owl.carousel");
						break;
					default:
				}
			});
		});
	});

} )( jQuery, mediaWiki );
