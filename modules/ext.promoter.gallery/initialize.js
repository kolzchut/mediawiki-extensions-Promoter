/* jshint jquery:true */
/* global mediaWiki */
( function ( $, mw ) {
	"use strict";

	var onInitial = function( $carousel ) {
		$carousel.removeClass( 'hidden' );
		randomizeOrder( $carousel );
	};

	var equalizeHeights = function() {
		$('.promotion-gallery').find( '.owl-item' ).equalizeCols();
	};

	var randomizeOrder = function( $carousel ) {
		$carousel.children().sort(function(){
			return Math.round(Math.random()) - 0.5;
		}).each(function(){
			$(this).appendTo($carousel);
		});

	};

	$(document).ready(function() {
		$(".promotion-gallery").each( function() {
			var $gallery = $(this);
			var $carousel = $gallery.find( '.owl-carousel' );

			$carousel.owlCarousel({
				responsive: {
					0: {
						items: 1
					},
					768: {
						items: 3
					}
				},
				items : 3,
				dots: false,
				//nav: true,
				//navContainer: '.gallery-controls',
				rtl: $('#mw-content-text').attr('dir') === 'rtl',
				onInitialized: onInitial( $carousel )
			});

			window.setTimeout( equalizeHeights, 500 );

			$gallery.find( '.owl-next' ).click( function( e ) {
				$carousel.trigger("next.owl.carousel");
				e.preventDefault();
			});
			$gallery.find( '.owl-prev' ).click( function( e ) {
				$carousel.trigger("prev.owl.carousel");
				e.preventDefault();
			});

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
