<?php
/**
 * Loads a specified ad, mainly for a forced preview
 */
class SpecialAdLoader extends UnlistedSpecialPage {
	/** @var string Name of the chosen ad */
	public $adName;

	/** @var boolean Should we display a page with ad preview? */
	protected $isPreview;

	function __construct() {
		// Register special page
		parent::__construct( "AdLoader" );
	}

	function execute( $par ) {
		$this->getParams();
		$html = '';
		$chosenAd = null;
		$error = false;

		try {
			$chosenAd = Ad::fromName( $this->adName );
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
		$request = $this->getRequest();
		$adName = $request->getText( 'ad' ) ?: null;
		$preview = ( $this->getSanitized( 'preview', Ad::BOOLEAN_PARAM_FILTER ) === 'true' );

		$required_values = [
			$adName
		];
		foreach ( $required_values as $value ) {
			if ( is_null( $value ) || $value === '' ) {
				throw new AdLoaderMissingRequiredParamsException();
			}
		}

		$this->adName = $adName;
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
	 * @throws SpecialAdLoaderException
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
class AdLoaderException extends MWException {
	function __construct( $adName = '(none provided)' ) {
		$this->message = get_called_class() . " while loading ad: '{$adName}'";
	}
}

class EmptyAdException extends AdLoaderException {
}

class AdLoaderMissingRequiredParamsException extends AdLoaderException {
}
