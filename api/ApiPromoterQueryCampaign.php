<?php

class ApiPromoterQueryCampaign extends ApiBase {

	const API_VERSION = '1.0';

	/**
	 * @var string sanitize campaign name
	 * FIXME: the string is apparently unrestricted in Special:Promoter
	 */
	const CAMPAIGNS_FILTER = '/[a-zA-Z0-9א-ת _()|\-]+/';

	public function execute() {
		// Obtain the ApiResults object from the base
		$result = $this->getResult();

		// Get our language/project/country
		$params = $this->extractRequestParams();

		$campaigns = explode( '|', $this->sanitizeText( $params['campaign'], static::CAMPAIGNS_FILTER ) );

		foreach ( $campaigns as $campaign ) {
			$settings = Campaign::getCampaignSettings( $campaign );
			if ( $settings ) {
				$settings['ads'] = json_decode( $settings['ads'] );

				# TODO this should probably be pushed down:
				$settings['enabled'] = $settings['enabled'] == '1';
				$settings['preferred'] = $settings['preferred'] == '1';
			}

			$result->addValue( array( $this->getModuleName() ), $campaign, $settings );
		}
	}

	public function getAllowedParams() {
		$params = array();

		$params['campaign'] = '';

		return $params;
	}

	public function getParamDescription() {
		$params = array();

		$params['campaign'] = "Campaign name. Separate multiple values with a \"|\" (vertical bar).";

		return $params;
	}

	public function getDescription() {
		return 'Get all configuration settings for a campaign.';
	}

	public function getVersion() {
		return 'PromoterQueryCampaign: ' . ApiPromoterQueryCampaign::API_VERSION;
	}

	/**
	 * Example API calls.
	 *
	 * @return array|bool|string
	 */
	public function getExamples() {
		return "api.php?action=promoterquerycampaign&format=json&campaign=Employment";
	}

	/**
	 * @static Obtains the parameter $param, sanitizes by returning the first match to $regex or
	 * $default if there was no match.
	 *
	 * @param string    $value    Incoming value
	 * @param string    $regex    Sanitization regular expression
	 * @param string    $default  Default value to return on error
	 *
	 * @return string The sanitized value
	 */
	private static function sanitizeText( $value, $regex, $default = null ) {
		$matches = array();

		if ( preg_match( $regex, $value, $matches ) ) {
			return $matches[ 0 ];
		} else {
			return $default;
		}
	}
}
