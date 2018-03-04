/* jshint jquery:true */
/* global mediaWiki */
( function ( $, mw ) {
	'use strict';

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
			$('.promotion-gallery').on( 'click', '.mainlink > a, a.caption', mw.promoterGallery.trackAd );
		},
		trackAd: function( event ) {
			if( mw.loader.getState( 'ext.googleUniversalAnalytics.utils' ) === null ) {
				return;
			}
			mw.loader.using( 'ext.googleUniversalAnalytics.utils' ).then( function() {
				var $link = $(event.target);
				var $ad = $link.closest('.promotion');
				var adName = $ad.data('adname');
				var campaign = mw.promoterGallery.page;

				mw.googleAnalytics.utils.recordClickEvent( event, {
					eventCategory: 'ad-gallery-clicks',
					eventAction: campaign,
					eventLabel: adName,
					nonInteraction: false
				});
			});

		},
		initialize: function () {
			$('.promotion-gallery').each(function () {
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
					$carousel.triggerHandler('next.owl.carousel');
					e.preventDefault();
				});
				$gallery.find('.owl-prev').click(function (e) {
					$carousel.triggerHandler('prev.owl.carousel');
					e.preventDefault();
				});

				$carousel.keydown(function (event) {
					switch (event.keyCode) {
						case 37:
							$carousel.triggerHandler('next.owl.carousel');
							break;
						case 39:
							$carousel.triggerHandler('prev.owl.carousel');
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
