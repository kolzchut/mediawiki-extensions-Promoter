<?php

class PromoterGallery {
	static public function onParserFirstCallInit( Parser &$parser ) {
		//$parser->setFunctionHook( 'promotergallery', array( $this, 'loadGallery' ) );
		$parser->setHook( 'promotergallery', array( __CLASS__, 'loadGallery' ) );
		return true;
	}

	/**
	 * Parser hook handler for {{#articletype}}
	 *
	 * @param Parser $parser : Parser instance available to render
	 *  wikitext into html, or parser methods.
	 *
	 * @return string: HTML to insert in the page.
	 */
	static public function loadGallery( $input, array $args, Parser $parser, PPFrame $frame ) {
		$parser->getOutput()->addModules( 'ext.promoter.gallery' );
		$pageName = $parser->getTitle()->getText();

		try {
			$renderedAds = array();
			$adChooser = new AdChooser( $pageName, !$parser->getUser()->isLoggedIn() );
			$ads = $adChooser->getAds();
			foreach( $ads as $ad ) {
				$renderedAds[] = Ad::fromName( $ad['name'] )->renderHtml();
			}

		} catch ( CampaignExistenceException $e ) {
			wfDebugLog( 'Promoter', $e->getMessage() );
			//@todo i18n
			return 'No campaign for this page';
		}

		$html = '<div class="promotion-gallery">';
		$html .= <<<HTML
<div class="gallery-controls">
	<span class="owl-prev icon icon-chevron-right icon-large" title="הקודם"></span>
	<span class="owl-next icon icon-chevron-left icon-large" title="הבא"></span>
</div>
HTML;
		if( $args['title'] ) {
			$html .= '<div class="header">' . $args['title'] . '</div>';
		}

		$html .= '<div class="owl-carousel clearfix" tabindex="0">'
			. implode( '', $renderedAds )
			. '</div>'
			. '</div>';

		return( $html );

	}
}
