<?php
/**
 * Loads all ads from a campaign. This will mainly be used on the Main Page
 */
class SpecialCampaignAdsLoader extends UnlistedSpecialPage {
	/** @var boolean Is user anonymous */
	public $anonymous;

	/** @var string Name of a campaign to load all ads from */
	public $campaignName;

	/** @var boolean Should we display a page with ad preview? */
	protected $isPreview;


	function __construct() {
		// Register special page
		parent::__construct( 'CampaignAdsLoader' );
	}

	function execute( $par ) {
		$this->getParams();
		$html = '';
		$error = false;
		$renderedAds = array();

		try {
			$campaign = new Campaign( $this->campaignName );
			$ads = $campaign->getAds();
			foreach( $ads as $ad ) {
				$renderedAds[] = Ad::fromName( $ad['name'] )->renderHtml();
			}
		} catch ( MWException $e ) {
			wfDebugLog( 'Promoter', $e->getMessage() );
			$error = $e->getMessage();
		}

		if( $this->isPreview ) {
			$this->setHeaders();
			if( $error ) {
				$html = "Exception {$error}.";
			} else {
				$html = '<div id="adPreview clearfix">';
				foreach ( $renderedAds as $ad ) {
					$html .= '<div class="col-sm-4">' .$ad . '</div>';
				}
				$html .= '</div>';
			}
			$this->getOutput()->addHTML( $html );
		} else {
			$this->getOutput()->disable();
			$this->sendHeaders();

			print_r ($renderedAds);
		}
	}

	function getParams() {
		$request = $this->getRequest();
		$campaignName = $request->getText( 'campaign' ) ?: null;
		$preview = ( $this->getSanitized( 'preview', Ad::BOOLEAN_PARAM_FILTER ) === 'true' );
		$anonymous = ( $this->getSanitized( 'anonymous', Ad::BOOLEAN_PARAM_FILTER ) === 'true' );

		$required_values = array(
			$campaignName
		);
		foreach ( $required_values as $value ) {
			if ( is_null( $value ) || $value === '' ) {
				throw new MissingRequiredParamsException();
			}
		}

		$this->anonymous = $anonymous;
		$this->campaignName = $campaignName;
		$this->isPreview = $preview;
	}

	function getSanitized( $param, $filter ) {
		$matches = array();
		if ( preg_match( $filter, $this->getRequest()->getText( $param ), $matches ) ) {
			return $matches[0];
		}
		return null;
	}

	/**
	 * Generate the HTTP response headers for the ad file
	 */
	function sendHeaders() {
		global $wgJsMimeType, $wgPromoterAdMaxAge;

		header( "Content-type: $wgJsMimeType; charset=utf-8" );

		if ( !$this->getUser()->isLoggedIn() ) {
			// Public users get cached
			header( "Cache-Control: public, s-maxage={$wgPromoterAdMaxAge}, max-age=0" );
		} else {
			// Private users do not (we have to emit this because we've disabled output)
			header( "Cache-Control: private, s-maxage=0, max-age=0" );
		}
	}

	/**
	 * Generate the JS for the requested ad
	 * @param Ad $ad
	 * @throws EmptyAdException
	 * @internal param string $adName
	 * @return string of Javascript containing a call to insertAd()
	 *   with JSON containing the ad content as the parameter
	 * @throw SpecialAdLoaderException
	 */
	public function getJsData( Ad &$ad ) {
		$adHtml = $ad->renderHtml();
		$adCaption = $ad->getCaption();

		if ( !$adHtml ) {
			throw new EmptyAdException( $ad->getName() );
		}

		$adArray = array(
			'adName' => $ad->getName(),
			'adCaption' => $adCaption,
			'adHtml' => $adHtml,
		);

		$adJson = FormatJson::encode( $adArray );

		$adJs = "mw.promoter.insertAd( {$adJson} );";

		return $adJs;
	}
}
