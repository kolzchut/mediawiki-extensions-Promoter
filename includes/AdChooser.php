<?php

class AdChooser {
	const SLOTS_KEY = 'slots';
	const ALLOCATION_KEY = 'allocation';
	const RAND_MAX = 30;

	protected $allocContext;

	protected $campaigns;
	protected $ads;

	/**
	 * @param array $campaigns structs of the type returned by getHistoricalCampaigns
	 * @param AllocationContext $allocContext used for filtering campaigns and ads
	 */
	function __construct( AllocationContext $allocContext, $campaigns = null ) {
		$this->allocContext = $allocContext;

		if ( $campaigns !== null ) {
			$this->campaigns = $campaigns;

			$this->ads = array();
			$this->filterCampaigns();
			foreach ( $this->campaigns as $campaign ) {
				foreach ( $campaign['ads'] as $name => $ad ) {
					$this->ads[] = $ad;
				}
			}
		} else {
			$this->campaigns = Campaign::getCampaigns();
			$this->ads = Ad::getCampaignAds( $this->campaigns );
		}
		$this->filterAds();
		$this->allocate();
	}

	/**
	 * @param $slot
	 * @return
	 * @internal param $rand [1-RAND_MAX]
	 */
	function chooseAd( $slot ) {
		// Convert slot to a float, [0-1]
		$slot = intval( $slot );
		if ( $slot < 1 || $slot > self::RAND_MAX ) {
			wfDebugLog( 'Promoter', "Illegal ad slot: {$slot}" );
			$slot = rand( 1, self::RAND_MAX );
		}

		// Choose an ad
		$counter = 0;
		foreach ( $this->ads as $ad ) {
			$counter += $ad[ self::SLOTS_KEY ];
			if ( $slot <= $counter ) {
				return $ad;
			}
		}
	}


	protected function filterCampaigns() {
		$filtered = array();

		foreach ( $this->campaigns as $campaign ) {

		/*
			$projectAllowed = (
				!$this->allocContext->getProject()
				or in_array( $this->allocContext->getProject(), $campaign['projects'] )
			);
			$languageAllowed = (
				!$this->allocContext->getLanguage()
				or in_array( $this->allocContext->getLanguage(), $campaign['languages'] )
			);
			$countryAllowed = (
				!$this->allocContext->getCountry()
				or !$campaign['geo']
				or in_array( $this->allocContext->getCountry(), $campaign['countries'] )
			);
			if ( $projectAllowed and $languageAllowed and $countryAllowed ) {
				$filtered[] = $campaign;
			}

	*/
		}
		$this->campaigns = $filtered;
	}


	/**
	 * From the selected group of ads we wish to now filter only for those that
	 * are relevant to the user. The ads choose if they display to anon/logged
	 * out, what device, and what bucket. They must also take into account their
	 * campaigns priority level.
	 *
	 * Logged In/Out and device are considered independent of the campaign priority
	 * for allocation purposes so are filtered for first.
	 *
	 * Then we filter for campaign dependent variables -- primarily the priority
	 * followed by the ad bucket.
	 */
	protected function filterAds() {
		// Filter on Logged
		if ( $this->allocContext->getAnonymous() !== null ) {
			$display_column = ( $this->allocContext->getAnonymous() ? 'display_anon' : 'display_user' );
			$this->filterAdsOnColumn( $display_column, 1 );
		}

		/*
		// Filter for device category
		if ( $this->allocContext->getDevice() ) {
			$this->filterAdsOnColumn( 'device', $this->allocContext->getDevice() );
		}

		// Filter for the provided bucket.
		$bucket = $this->allocContext->getBucket();
		$this->ads = array_filter(
			$this->ads,
			function ( $ad ) use ( $bucket ) {
				global $wgNoticeNumberOfBuckets;

				// In case we change the number of buckets available, will map
				// the ad bucket down
				$adBucket = intval( $ad[ 'bucket' ] ) % $wgNoticeNumberOfBuckets;

				// Actual mapping. It is assumed the user always was randomly choosing out
				// of a ring with $wgNoticeNumberOfBuckets choices. This implies that we will
				// always be mapping the ring down, never up.
				$userBucket = intval( $bucket ) % intval( $ad[ 'campaign_num_buckets' ] );

				return ( $adBucket === $userBucket );
			}
		);
		*/

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
		// Normalize ads to a proportion of the total campaign weight.
		$campaignTotalWeights = array();
		foreach ( $this->ads as $ad ) {
			if ( empty( $campaignTotalWeights[$ad['campaign']] ) ) {
				$campaignTotalWeights[$ad['campaign']] = 0;
			}
			$campaignTotalWeights[$ad['campaign']] += $ad['weight'];
		}
		foreach ( $this->ads as &$ad ) {
			// Adjust the maximum allocation for the ad according to
			// campaign throttle settings.  The max_allocation would be
			// this ad's allocation if only one campaign were present.
			$ad['max_allocation'] = ( $ad['weight'] / $campaignTotalWeights[$ad['campaign']] )
				* ( $ad['campaign_throttle'] / 100.0 );
		}

		// Collect ads by priority level, and determine total desired
		// allocation for each level.
		$priorityTotalAllocations = array();
		$priorityAds = array();
		foreach ( $this->ads as &$ad ) {
			$priorityAds[$ad['campaign_z_index']][] = &$ad;

			if ( empty( $priorityTotalAllocations[$ad['campaign_z_index']] ) ) {
				$priorityTotalAllocations[$ad['campaign_z_index']] = 0;
			}
			$priorityTotalAllocations[$ad['campaign_z_index']] += $ad['max_allocation'];
		}

		// Distribute allocation by priority.
		$remainingAllocation = 1.0;
		// Order by priority, descending.
		krsort( $priorityAds );
		foreach ( $priorityAds as $z_index => $ads ) {
			if ( $remainingAllocation <= 0.01 ) {
				// Don't show ads at lower priority levels if we've used up
				// the full 100% already.
				foreach ( $ads as &$ad ) {
					$ad[self::ALLOCATION_KEY] = 0;
				}
				continue;
			}

			if ( $priorityTotalAllocations[$z_index] > $remainingAllocation ) {
				$scaling = $remainingAllocation / $priorityTotalAllocations[$z_index];
				$remainingAllocation = 0;
			} else {
				$scaling = 1;
				$remainingAllocation -= $priorityTotalAllocations[$z_index];
			}
			foreach ( $ads as &$ad ) {
				$ad[self::ALLOCATION_KEY] = $ad['max_allocation'] * $scaling;
			}

		}

		// To be deprecated by continuous allocation:
		$this->quantizeAllocationToSlots();
	}

	/**
	 * Take ad allocations in [0, 1] real form and convert to slots.
	 * Adjust the real form to reflect final slot numbers.
	 */
	function quantizeAllocationToSlots() {
		// Sort the ads by weight, smallest to largest.  This helps
		// prevent allocating zero slots to an ad, by rounding in
		// favor of the ads with smallest allocations.
		$alloc_key = self::ALLOCATION_KEY;
		usort( $this->ads, function( $a, $b ) use ( $alloc_key ) {
				return ( $a[$alloc_key] >= $b[$alloc_key] ) ? 1 : -1;
			} );

		// First pass: allocate the minimum number of slots to each ad,
		// giving at least one slot per ad up to RAND_MAX slots.
		$sum = 0;
		foreach ( $this->ads as &$ad ) {
			$slots = intval( max( floor( $ad[self::ALLOCATION_KEY] * self::RAND_MAX ), 1 ) );

			// Don't give any slots if the ad is hidden due to e.g. priority level
			if ( $ad[self::ALLOCATION_KEY] == 0 ) {
				$slots = 0;
			}

			// Compensate for potential overallocation
			if ( $slots + $sum > self::RAND_MAX ) {
				$slots = self::RAND_MAX - $sum;
			}

			$ad[self::SLOTS_KEY] = $slots;
			$sum += $slots;
		}

		// Second pass: allocate each remaining slot one at a time to each
		// ad if they are underallocated
		foreach ( $this->ads as &$ad ) {
			if ( $sum >= self::RAND_MAX ) {
				break;
			}
			if ( ( $ad[self::ALLOCATION_KEY] * self::RAND_MAX ) > $ad[self::SLOTS_KEY] ) {
				$ad[self::SLOTS_KEY] += 1;
				$sum += 1;
			}
		}

		// Refresh allocation levels according to quantization
		foreach ( $this->ads as &$ad ) {
			$ad[self::ALLOCATION_KEY] = $ad[self::SLOTS_KEY] / self::RAND_MAX;
		}
	}

	/**
	 * @return array of campaigns after filtering on criteria
	 */
	function getCampaigns() {
		return $this->campaigns;
	}

	/**
	 * @return array of ads after filtering on criteria
	 */
	function getAds() {
		return $this->ads;
	}
}
