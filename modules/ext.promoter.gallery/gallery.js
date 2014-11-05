/* jshint jquery:true */
/* global mediaWiki */
( function ( $, mw ) {
	"use strict";


	mw.promoter.gallery = {

		page: mw.config.get( 'wgPageName' ),

		onInitial: function ($carousel) {
			$carousel.closest('.promotion-gallery').removeClass('hidden');
			mw.promoter.gallery.randomizeOrder($carousel);
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
			$('.promotion-gallery').on( 'click', '.mainlink > a, a.caption', mw.promoter.gallery.trackAd );
		},
		trackAd: function( e ) {
			var $link = $( e.target );
			var $ad = $link.closest( '.promotion' );
			var adName = $ad.data( 'adname' );
			var campaign = mw.promoter.gallery.page;

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
					onInitialized: mw.promoter.gallery.onInitial($carousel)
				});

				window.setTimeout(mw.promoter.gallery.equalizeHeights, 500);

				$gallery.find('.owl-next').click(function (e) {
					$carousel.trigger("next.owl.carousel");
					e.preventDefault();
				});
				$gallery.find('.owl-prev').click(function (e) {
					$carousel.trigger("prev.owl.carousel");
					e.preventDefault();
				});

				$carousel.keydown(function (event) {
					switch (event.key) {
						case 'Left':
							$carousel.trigger("next.owl.carousel");
							break;
						case 'Right':
							$carousel.trigger("prev.owl.carousel");
							break;
						default:
					}
				});
			});

			mw.promoter.gallery.enableAdTracking();
		}
	};

	$( function() {
		mw.promoter.gallery.initialize();
	});
} )( jQuery, mediaWiki );
