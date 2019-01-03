<?php

class PromoterGallery {
	public static function onParserFirstCallInit( Parser &$parser ) {
		$parser->setHook( 'promotergallery', [ __CLASS__, 'renderGallery' ] );
		return true;
	}

	/**
	 * Parser hook handler for {{#articletype}}
	 *
	 * @param $input : unused
	 * @param array $args
	 * @param Parser $parser : Parser instance available to render
	 *  wikitext into html, or parser methods.
	 * @param PPFrame $frame : unsed
	 *
	 * @return string HTML to insert in the page.
	 */
	public static function renderGallery( $input, array $args, Parser $parser, PPFrame $frame ) {
		$parser->getOutput()->addModules( 'ext.promoter.gallery' );
		$pageName = $parser->getTitle()->getText();

		try {
			$renderedAds = [];
			$adChooser = new AdChooser( $pageName, !$parser->getUser()->isLoggedIn() );
			$ads = $adChooser->getAds();
			foreach ( $ads as $ad ) {
				$renderedAds[] = Ad::fromName( $ad['name'] )->renderHtml();
			}
		} catch ( AdCampaignExistenceException $e ) {
			wfDebugLog( 'Promoter', $e->getMessage() );
			// @todo i18n
			return '<span class="error">No campaign for this page</span>';
		} catch ( MWException $e ) {
			wfDebugLog( 'Promoter', $e->getMessage() );
			return '<span class="error text-danger">An error occurred [' . $e->getMessage() . ']</span>';
		}

		$html = '<div class="promotion-gallery hidden hidden-print">'
			. '<h5 class="sr-only">זוהי גלריה המקדמת ערכים שונים באתר.</h5>'
			. '<div class="gallery-controls">'
				. '<span class="sr-only">בכל רגע מוצגות 3 ידיעות בגלריה. ניתן להציג ידיעה נוספת או לחזור לאחור באמצעות הכפתורים הבאים, או באמצעות מקשי החיצים כאשר הפוקוס הוא על הגלריה</span>'
				. '<a href="#" class="owl-prev"><span class="fa fa-chevron-right fa-lg" title="הקודם"></span><span class="sr-only">הצגת הידיעה הקודמת</span></a>'
				. '<a href="#" class="owl-next"><span class="fa fa-chevron-left fa-lg" title="הבא"></span><span class="sr-only">הצגת הידיעה הבאה</span></a>'
			. '</div>';
		if ( $args['title'] ) {
			$html .= '<div class="header">' . $args['title'] . '</div>';
		}

		$html .= '<div class="owl-carousel clearfix" tabindex="0">'
			. implode( '', $renderedAds )
			. '</div>'
			. '</div>';

		return $html;
	}
}
