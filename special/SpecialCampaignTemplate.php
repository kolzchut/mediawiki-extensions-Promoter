<?php

class SpecialCampaignTemplate extends Promoter {
	var $editable, $promoterError;

	function __construct() {
		// Register special page
		SpecialPage::__construct( 'CampaignTemplate' );
	}

	public function isListed() {
		return false;
	}

	/**
	 * Handle different types of page requests
	 */
	public function execute( $sub ) {
		if ( $sub == 'view' ) {
			// Trying to view an ad -- so redirect to edit form
			$ad = $this->getRequest()->getText( 'ad' );

			$this->getOutput()->redirect(
				Title::makeTitle( NS_SPECIAL, "PromoterAds/edit/$ad" )->
					getCanonicalURL(),
				301
			);
		} else {
			// don't know where they were trying to go, redirect them to the new list form
			$this->getOutput()->redirect(
				Title::makeTitle( NS_SPECIAL, 'PromoterAds' )->getCanonicalURL(),
				301
			);
		}
	}
}
