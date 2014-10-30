<?php

class AdChooser {
	const SLOTS_KEY = 'slots';
	const ALLOCATION_KEY = 'allocation';
	const RAND_MAX = 30;

	protected $campaignName;
	protected $ads;
	protected $anonymous;
	protected $campaignTotalWeight;

	/**
	 * @param string $campaignName
	 * @param bool $anonymous
	 * @throws NoAdsMatchingCriteriaException
	 * @throws NoFallbackCampaign
	 * @throws FallbackCampaignDisabled
	 */
	function __construct( $campaignName, $anonymous = false ) {
		global $wgPromoterFallbackCampaign;
		$this->anonymous = $anonymous;

		$campaign = new Campaign( $campaignName );

		if ( !Campaign::campaignExists( $campaignName ) || !$campaign->isEnabled() ) {
			/* If the selected campaign doesn't exist or is disabled, fallback: */
			$campaign = new Campaign( $wgPromoterFallbackCampaign );
			if ( !Campaign::campaignExists( $wgPromoterFallbackCampaign )  ) {
				throw new NoFallbackCampaign();
			} elseif ( !$campaign->isEnabled() ) {
				throw new FallbackCampaignDisabled();
			}
		}

		$this->campaignName = $campaign->getName();
		$this->ads = $campaign->getAds();

		$this->filterAds();

		if( count( $this->ads ) < 1 ) {
			throw new NoAdsMatchingCriteriaException( $this->campaignName );
		}

		/*
		echo '<pre dir="ltr">';
		print_r( $this->ads );
		echo '</pre>';
		*/


		$this->allocate();



		//$chosenAd = $this->chooseAd();

	}

	/**
	 * @return mixed
	 */
	function chooseAd() {
		/*
			$numAds = count( $this->ads );
			$randomAd = mt_rand( 0, $numAds-1 );
		*/

		$randomNum = mt_rand( 0, $this->campaignTotalWeight );
		$weightCounter = 0;

		foreach( $this->ads as $ad ) {
			$weightCounter += $ad['weight'];
			if ( $weightCounter > $randomNum ) {
				return $ad;
			}
		}

	}



	/**
	 * From the selected group of ads we wish to now filter only for those that
	 * are relevant to the user. The ads choose if they display to anon/logged
	 * out
	 */
	protected function filterAds() {
		// Filter on Logged
		if ( $this->anonymous !== null ) {
			$display_column = ( $this->anonymous ? 'display_anon' : 'display_user' );
			$this->filterAdsOnColumn( $display_column, 1 );
		}

		// Reset the keys
		$this->ads = array_values( $this->ads );
	}

	protected function filterAdsOnColumn( $key, $value ) {
		$this->ads = array_filter(
			$this->ads,
			function( $ad ) use ( $key, $value ) {
				return ( $ad[$key] === $value );
			}
		);
	}

	/**
	 * Calculate allocation proportions and store them in the ads.
	 */
	protected function allocate() {
		$this->campaignTotalWeight = 0;
		foreach( $this->ads as $ad ) {
			$this->campaignTotalWeight += $ad['weight'];
		}
	}

	/**
	 * @return array of ads after filtering on criteria
	 */
	function getAds() {
		return $this->ads;
	}
}

class NoAdsMatchingCriteriaException extends MWException {
	function __construct( $campaignName ) {
		$this->message = get_called_class() . ": while loading campaign: '{$campaignName}'";
	}
}

class NoFallbackCampaign extends MWException {
	function __construct() {
		global $wgPromoterFallbackCampaign;
		$this->message = get_called_class() . ": No campaign was found, not even the fallback '" . $wgPromoterFallbackCampaign ."' campaign";
	}
}

class FallbackCampaignDisabled extends MWException {
	function __construct() {
		global $wgPromoterFallbackCampaign;
		$this->message = get_called_class() . ": The fallback campaign, '" . $wgPromoterFallbackCampaign .",' is disabled";
	}
}

