<?php
/**
 * Renders ad contents as jsonp, making a random selection from a campaign
 */
class SpecialAdRandom extends UnlistedSpecialPage {
	/** @var boolean Is user anonymous */
	public $anonymous;

	/** @var string Name of the campaign that the ad belongs to.*/
	public $campaignName;

	/** @var boolean Should we display a page with ad preview? */
	protected $isPreview;

	function __construct() {
		// Register special page
		parent::__construct( "AdRandom" );
	}

	function execute( $par ) {
		$this->getParams();
		$html = '';
		$chosenAd = null;
		$error = false;

		try {
			$adChooser = new AdChooser( $this->campaignName, $this->anonymous );
			$chosenAd = $adChooser->chooseAd();
			$chosenAd = Ad::fromName( $chosenAd['name'] );
			$html = $chosenAd->renderHtml();
		} catch ( MWException $e ) {
			wfDebugLog( 'Promoter', $e->getMessage() );
			$error = $e->getMessage();
		}

		if ( $this->isPreview ) {
			$this->setHeaders();
			$html = $error ?
				"Exception {$error}." : '<div id="adPreview" class="col-md-3 col-sm-4">' . $html . '</div>';
			$this->getOutput()->addHTML( $html );
		} else {
			$this->getOutput()->disable();
			$this->sendHeaders();

			if ( $error ) {
				echo "mw.promoter.adController.insertAd( false /* due to internal exception ({$error}) */ );";
			} else {
				echo $this->getJsData( $chosenAd );
			}
		}
	}

	function getParams() {
		global $wgPromoterFallbackCampaign;

		$request = $this->getRequest();

		$anonymous = ( $this->getSanitized( 'anonymous', Ad::BOOLEAN_PARAM_FILTER ) === 'true' );
		$campaignName = $request->getText( 'campaign' ) ?: $wgPromoterFallbackCampaign;
		$preview = ( $this->getSanitized( 'preview', Ad::BOOLEAN_PARAM_FILTER ) === 'true' );

		$required_values = [
			$campaignName,
			$anonymous
		];
		foreach ( $required_values as $value ) {
			if ( is_null( $value ) || $value === '' ) {
				throw new AdLoaderMissingRequiredParamsException();
			}
		}

		$this->anonymous = $anonymous;
		$this->campaignName = $campaignName;
		$this->isPreview = $preview;
	}

	function getSanitized( $param, $filter ) {
		$matches = [];
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
	 */
	public function getJsData( Ad &$ad ) {
		$adHtml = $ad->renderHtml();
		$adCaption = $ad->getCaption();

		if ( !$adHtml ) {
			throw new EmptyAdException( $ad->getName() );
		}

		$adArray = [
			'adName' => $ad->getName(),
			'adCaption' => $adCaption,
			'adHtml' => $adHtml,
			'campaign' => $this->campaignName,
		];

		$adJson = FormatJson::encode( $adArray );

		$adJs = "mw.promoter.adController.insertAd( {$adJson} );";

		return $adJs;
	}
}

/**
 * @defgroup Exception Exception
 */

/**
 * These exceptions are thrown whenever an error occurs, which is fatal to
 * rendering the ad, but can be fairly expected.
 *
 * @ingroup Exception
 */
class AdRandomException extends MWException {
	function __construct( $campaignName = '(none provided)' ) {
		$this->message = get_called_class() . " while loading campaign: '{$campaignName}'";
	}
}
