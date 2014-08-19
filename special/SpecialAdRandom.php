<?php
/**
 * Renders banner contents as jsonp, making a random selection from a
 * predetermined number of slots.
 */
class SpecialAdRandom extends SpecialAdLoader {
	const SLOT_FILTER = '/[0-9]+/';

	function __construct() {
		// Register special page
		UnlistedSpecialPage::__construct( "AdRandom" );
	}

	function getParams() {
		parent::getParams();

		$this->slot = $this->getSanitized( 'slot', self::SLOT_FILTER );

		if ( $this->slot === null ) {
			throw new MissingRequiredParamsException();
		}

		$this->chooseAd();
	}

	protected function chooseAd() {
		$chooser = new AdChooser( $this->allocContext );
		$banner = $chooser->chooseAd( $this->slot );

		if ( $banner ) {
			$this->adName = $banner['name'];
			$this->campaignName = $banner['campaign'];
		}
	}

	function sendHeaders() {
		global $wgJsMimeType, $wgNoticeBannerMaxAge;

		header( "Content-type: $wgJsMimeType; charset=utf-8" );

		// If we have a logged in user; do not cache (default for special pages)
		// lest we capture a set-cookie header. Otherwise cache so we don't have
		// too big of a DDoS hole.
		if ( !$this->getUser()->isLoggedIn() ) {
			header( "Cache-Control: public, s-maxage={$wgNoticeBannerMaxAge}, max-age=0" );
		}
	}
}
