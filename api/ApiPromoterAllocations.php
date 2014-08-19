<?php

/**
 * Implementation of the query=promoterallocations API call. This call returns the expected ad
 * allocation for the given parameters
 */
class ApiPromoterAllocations extends ApiBase {

	const API_VERSION = '1.0';
	const DEFAULT_ANONYMOUS = 'true';

	/**
	 * @var string Pattern for bool
	 */
	const ANONYMOUS_FILTER = '/true|false/';


	public function execute() {
		// Obtain the ApiResults object from the base
		$result = $this->getResult();

		// Get our language/project/country
		$params = $this->extractRequestParams();

		$adList = static::getAdAllocation(
			$params['anonymous']
		);

		$result->setIndexedTagName( $adList, 'AdAllocation' );
		$result->addValue( $this->getModuleName(), 'ads', $adList );
	}

	public function getAllowedParams() {
		$params = array();

		$params['anonymous']= ApiPromoterAllocations::DEFAULT_ANONYMOUS;

		return $params;
	}

	public function getParamDescription() {
		$params = array();
		$params['anonymous']= "The logged-in status to filter on (true|false)";

		return $params;
	}

	public function getDescription() {
		return 'Obtain the ad allocations for ads served by Promoter for all user types under the parametric filter. This is a JSON only call.';
	}

	public function getVersion() {
		return 'PromoterAllocations: ' . ApiPromoterAllocations::API_VERSION;
	}

	/**
	 * Example API calls.
	 *
	 * @return array|bool|string
	 */
	public function getExamples() {
		return "api.php?action=promoterallocations&format=json&anonymous=true";
	}

	/**
	 * MediaWiki interface to this API call -- obtains ad allocation information; ie how many
	 * buckets there are in a campaign, and what ads should be displayed for a given filter.
	 *
	 * Returns results as an array of ads
	 *  - ads
	 *
	 *              - name          The name of the ad
	 *              - allocation    What the allocation proportion (0 to 1) should be
	 *              - campaign      The name of the associated campaign
	 *              - weight            The assigned weight in the campaign
	 *              - display_anon      1 if should be displayed to anonymous users
	 *              - display_account   1 if should be displayed to logged in users
	 *
	 * @param string $anonymous - Is user anonymous, eg 'true'
	 *
	 * @return array
	 */
	public static function getAdAllocation( $anonymous ) {
		$anonymous = ApiPromoterAllocations::sanitizeText(
			$anonymous,
			self::ANONYMOUS_FILTER,
			self::DEFAULT_ANONYMOUS
		);
		$anonymous = ( $anonymous == 'true' );

		$allocContext = new AllocationContext( $anonymous );

		$chooser = new AdChooser( $allocContext );
		$ads = $chooser->getAds();

		return $ads;
	}

	/**
	 * @static Obtains the parameter $param, sanitizes by returning the first match to $regex or
	 * $default if there was no match.
	 *
	 * @params array    $params   Array of parameters to extract data from
	 * @param string    $param    Name of GET/POST parameter
	 * @param string    $regex    Sanitization regular expression
	 * @param string    $default  Default value to return on error
	 *
	 * @return string The sanitized value
	 */
	private static function sanitizeText( $param, $regex, $default = null ) {
		$matches = array();

		if ( preg_match( $regex, $param, $matches ) ) {
			return $matches[ 0 ];
		} else {
			return $default;
		}
	}
}
