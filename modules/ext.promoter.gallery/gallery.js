/* jshint jquery:true */
/* global mediaWiki */
( function ( $, mw ) {
	"use strict";


	mw.promoterGallery = {

		page: mw.config.get( 'wgPageName' ),

		onInitial: function ($carousel) {
			$carousel.closest('.promotion-gallery').removeClass('hidden');
			mw.promoterGallery.randomizeOrder($carousel);
		},

		equalizeHeights: function () {
			$('.promotion-gallery').find('.owl-item').equalizeCols();
		},

		randomizeOrder: function ($carousel) {
			$carousel.children().sort(function () {
				return Math.round(Math.random()) - 0.5;
			}).each(function () {
				$(this).appendTo($carousel);
			});
		},
		enableAdTracking: function() {
			window._gaq = window._gaq || []; // Make sure there's a queue for GA
			$('.promotion-gallery').on( 'click', '.mainlink > a, a.caption', mw.promoterGallery.trackAd );
		},
		trackAd: function( e ) {
			var $link = $( e.target );
			var $ad = $link.closest( '.promotion' );
			var adName = $ad.data( 'adname' );
			var campaign = mw.promoterGallery.page;

			_gaq.push(['_set', 'hitCallback', function () {
				document.location = e.target.href;	// Navigate on hit callback
			}]);
			window._gaq.push( ['_trackEvent', 'ad-clicks', campaign, adName] ); // Send hit

			return !window._gat; // Prevent default nav only if GA is loaded
			//setTimeout('document.location = "' + e.target.href + '"', 100); // Allow time for hit
		},
		initialize: function () {
			$(".promotion-gallery").each(function () {
				var $gallery = $(this);
				var $carousel = $gallery.find('.owl-carousel');

				$carousel.owlCarousel({
					responsive: {
						0: {
							items: 1
						},
						768: {
							items: 3
						}
					},
					items: 3,
					dots: false,
					//nav: true,
					//navContainer: '.gallery-controls',
					rtl: $('#mw-content-text').attr('dir') === 'rtl',
					onInitialized: mw.promoterGallery.onInitial($carousel)
				});

				window.setTimeout(mw.promoterGallery.equalizeHeights, 500);

				$gallery.find('.owl-next').click(function (e) {
					$carousel.triggerHandler("next.owl.carousel");
					e.preventDefault();
				});
				$gallery.find('.owl-prev').click(function (e) {
					$carousel.triggerHandler("prev.owl.carousel");
					e.preventDefault();
				});

				$carousel.keydown(function (event) {
					switch (event.keyCode) {
						case 37:
							$carousel.triggerHandler("next.owl.carousel");
							break;
						case 39:
							$carousel.triggerHandler("prev.owl.carousel");
							break;
						default:
					}
				});
			});

			mw.promoterGallery.enableAdTracking();
		}
	};

	$( function() {
		mw.promoterGallery.initialize();
	});
} )( jQuery, mediaWiki );
