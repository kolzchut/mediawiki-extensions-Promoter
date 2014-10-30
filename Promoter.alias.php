<?php
/**
 * Aliases for special pages of Promoter extension.
 */
// @codingStandardsIgnoreFile

$specialPageAliases = array();

/** English (English) */
$specialPageAliases['en'] = array(
	'Promoter' => array( 'Promoter' ),
	'PromoterAds' => array( 'PromoterAds' ),
	'CampaignAd' => array( 'CampaignAd' ),
	'GlobalAllocation' => array( 'GlobalAllocation' ),
	'AdAllocation' => array( 'AdAllocation' ),
	'AdController' => array( 'AdController' ),
	'AdLoader' => array( 'AdLoader' ),
	'AdRandom' => array( 'AdRandom' ),
	'CampaignAdsLoader' => array( 'CampaignAdsLoader', 'Campaign Ads Loader' ),
);

/** Hebrew (עברית) */
$specialPageAliases['he'] = array(
	'AdLoader' => array( 'טעינת_פרסומת' ),
	'AdRandom' => array( 'טעינת_פרסומת_אקראית' ),
	'CampaignAdsLoader' => array( 'טעינת_פרסומות_קמפיין' ),
);
