<?php

namespace MediaWiki\Extension\Promoter;

use Html;
use MediaWiki\Extension\Promoter\Special\SpecialPromoter;
use Xml;

class PromoterPager extends AdPager {
	/**
	 * Pull ads from the database
	 *
	 * @return array
	 */
	public function getQueryInfo() {
		$dbr = PRDatabase::getDb();

		// First we must construct the filter before we pull ads
		// When the filter comes in it is space delimited, so break that...
		$likeArray = preg_split( '/\s/', $this->filter );

		// ...and then insert all the wildcards betwean search terms
		if ( empty( $likeArray ) ) {
			$likeArray = $dbr->anyString();
		} else {
			$anyStringToken = $dbr->anyString();
			$tempArray = [ $anyStringToken ];
			foreach ( $likeArray as $likePart ) {
				$tempArray[ ] = $likePart;
				$tempArray[ ] = $anyStringToken;
			}
			$likeArray = $tempArray;
		}

		// Get the current campaign and filter on that as well if required
		$campaign = $this->mRequest->getVal( 'campaign' );
		$campaignId = AdCampaign::getCampaignId( $campaign );

		if ( $campaignId ) {
			// Return all the ads not already assigned to the current campaign
			return [
				'tables' => [
					'adlinks' => 'pr_adlinks',
					'ads' => 'pr_ads',
				],

				'fields' => [ 'ads.ad_name', 'ads.ad_id' ],

				'conds' => [
					'adlinks.ad_id IS NULL',
					'ad_name' . $dbr->buildLike( $likeArray )
				],

				'join_conds' => [
					'adlinks' => [
						'LEFT JOIN',
						"adlinks.ad_id = ads.ad_id " .
							"AND adlinks.cmp_id = $campaignId"
					]
				]
			];
		} else {
			// Return all the ads in the database
			return [
				'tables' => [ 'ads' => 'pr_ads' ],
				'fields' => [ 'ads.ad_name', 'ads.ad_id' ],
				'conds'  => [ 'ads.ad_name' . $dbr->buildLike( $likeArray ) ],
			];
		}
	}

	/**
	 * Generate the content of each table row (1 row = 1 ad)
	 *
	 * @param array|\stdClass $row Database row
	 *
	 * @return string
	 * @throws AdDataException
	 */
	public function formatRow( $row ) {
		// Begin ad row
		$htmlOut = Xml::openElement( 'tr' );

		if ( $this->editable ) {
			// Add box
			$htmlOut .= Xml::openElement( 'td', [ 'valign' => 'top' ] );
			$htmlOut .= Html::openElement( 'label', [ 'class' => 'checkbox-label' ] );
			$htmlOut .= Xml::check( 'addAds[]', '', [ 'value' => $row->ad_id ] );
			$htmlOut .= Html::closeElement( 'label' );
			$htmlOut .= Html::closeElement( 'td' );
		}

		// Link and Preview
		$ad = Ad::fromId( $row->ad_id );

		$htmlOut .= Xml::tags( 'td', [ 'valign' => 'top' ],
			$ad->linkToEdit()
		);

		// End ad row
		$htmlOut .= Xml::closeElement( 'tr' );

		return $htmlOut;
	}

	/**
	 * Specify table headers
	 *
	 * @return string
	 */
	protected function getStartBody() {
		$htmlOut = Xml::openElement( 'table', [ 'cellpadding' => 9 ] );
		$htmlOut .= Xml::openElement( 'tr' );
		if ( $this->editable ) {
			$htmlOut .= Xml::element( 'th', [ 'width' => '5%' ],
				$this->msg( 'promoter-add' )->text()
			);
		}
		$htmlOut .= Xml::element( 'th', null,
			$this->msg( 'promoter-ads' )->text()
		);
		$htmlOut .= Xml::closeElement( 'tr' );
		return $htmlOut;
	}

	/**
	 * Close table
	 *
	 * @return string
	 */
	protected function getEndBody() {
		return Xml::closeElement( 'table' );
	}
}
