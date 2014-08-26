<?php

class PromoterPager extends AdPager {
	var $viewPage, $special;
	var $editable;
	var $filter;

	function __construct( $special, $filter = '' ) {
		parent::__construct( $special, $filter );
	}

	/**
	 * Pull ads from the database
	 */
	function getQueryInfo() {
		$dbr = PRDatabase::getDb();

		// First we must construct the filter before we pull ads
		// When the filter comes in it is space delimited, so break that...
		$likeArray = preg_split( '/\s/', $this->filter );

		// ...and then insert all the wildcards betwean search terms
		if ( empty( $likeArray ) ) {
			$likeArray = $dbr->anyString();
		} else {
			$anyStringToken = $dbr->anyString();
			$tempArray = array( $anyStringToken );
			foreach ( $likeArray as $likePart ) {
				$tempArray[ ] = $likePart;
				$tempArray[ ] = $anyStringToken;
			}
			$likeArray = $tempArray;
		}

		// Get the current campaign and filter on that as well if required
		$campaign = $this->mRequest->getVal( 'campaign' );
		$campaignId = Campaign::getCampaignId( $campaign );

		if ( $campaignId ) {
			// Return all the ads not already assigned to the current campaign
			return array(
				'tables' => array(
					'adlinks' => 'pr_adlinks',
					'ads' => 'pr_ads',
				),

				'fields' => array( 'ads.ad_name', 'ads.ad_id' ),

				'conds' => array(
					'adlinks.ad_id IS NULL',
					'ad_name' . $dbr->buildLike( $likeArray )
				),

				'join_conds' => array(
					'adlinks' => array(
						'LEFT JOIN',
						"adlinks.ad_id = ads.ad_id " .
							"AND adlinks.cmp_id = $campaignId"
					)
				)
			);
		} else {
			// Return all the ads in the database
			return array(
				'tables' => array( 'ads' => 'pr_ads'),
				'fields' => array( 'ads.ad_name', 'ads.ad_id' ),
				'conds'  => array( 'ads.ad_name' . $dbr->buildLike( $likeArray ) ),
			);
		}
	}

	/**
	 * Generate the content of each table row (1 row = 1 ad)
	 */
	function formatRow( $row ) {
		// Begin ad row
		$htmlOut = Xml::openElement( 'tr' );

		if ( $this->editable ) {
			// Add box
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
				Xml::check( 'addAds[]', '', array( 'value' => $row->ad_name ) )
			);
			// Weight select
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top', 'class' => 'pr-weight' ),
				Xml::listDropDown( "weight[$row->ad_id]",
					Promoter::dropDownList(
						$this->msg( 'promoter-weight' )->text(), range( 0, 100, 5 )
					),
					'',
					'25',
					'',
					'' )
			);
		}

		// Link and Preview
		$ad = Ad::fromName( $row->ad_name );

		$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
			$ad->linkToPreview()
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
	function getStartBody() {
		$htmlOut = '';
		$htmlOut .= Xml::openElement( 'table', array( 'cellpadding' => 9 ) );
		$htmlOut .= Xml::openElement( 'tr' );
		if ( $this->editable ) {
			$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'width' => '5%' ),
				$this->msg( 'promoter-add' )->text()
			);
			$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'width' => '5%', 'class' => 'pr-weight' ),
				$this->msg( 'promoter-weight' )->text()
			);
		}
		$htmlOut .= Xml::element( 'th', array( 'align' => 'left' ),
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
	function getEndBody() {
		return Xml::closeElement( 'table' );
	}
}
