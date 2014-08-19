<?php
/**
 * Renders ad contents as jsonp.
 */
class SpecialAdLoader extends UnlistedSpecialPage {
	/** @var string Name of the chosen ad */
	public $adName;

	/** @var string Name of the campaign that the ad belongs to.*/
	public $campaignName;

	public $allocContext = null;

	function __construct() {
		// Register special page
		parent::__construct( "AdLoader" );
	}

	function execute( $par ) {
		$this->sendHeaders();
		$this->getOutput()->disable();

		try {
			$this->getParams();
			echo $this->getJsNotice( $this->adName );
		} catch ( EmptyAdException $e ) {
			echo "mw.promoter.insertAd( false );";
		} catch ( MWException $e ) {
			wfDebugLog( 'CentralNotice', $e->getMessage() );
			echo "mw.promoter.insertAd( false /* due to internal exception */ );";
		}
	}

	function getParams() {
		$request = $this->getRequest();

		$anonymous = ( $this->getSanitized( 'anonymous', ApiPromoterAllocations::ANONYMOUS_FILTER ) === 'true' );

		$required_values = array(
			$anonymous
		);
		foreach ( $required_values as $value ) {
			if ( is_null( $value ) ) {
				throw new MissingRequiredParamsException();
			}
		}

		$this->allocContext = new AllocationContext(
			$anonymous
		);

		$this->campaignName = $request->getText( 'campaign' );
		$this->adName = $request->getText( 'ad' );
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
	 * @param $adName string
	 * @throws EmptyAdException
	 * @return string of Javascript containing a call to insertAd()
	 *   with JSON containing the ad content as the parameter
	 * @throw SpecialAdLoaderException
	 */
	public function getJsNotice( $adName ) {
		$ad = Ad::fromName( $adName );
		if ( !$ad->exists() ) {
			throw new EmptyAdException( $adName );
		}
		$adRenderer = new AdRenderer( $this->getContext(), $ad, $this->campaignName, $this->allocContext );

		$adHtml = $adRenderer->toHtml();

		if ( !$adHtml ) {
			throw new EmptyAdException( $adName );
		}

		// TODO: these are AdRenderer duties:
		$settings = Ad::getAdSettings( $adName, false );

		$adArray = array(
			'adName' => $adName,
			'adHtml' => $adHtml,
			'campaign' => $this->campaignName,
		);

		$adJson = FormatJson::encode( $adArray );

		$adJs = "mw.promoter.insertAd( {$adJson} );";

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

class MissingRequiredParamsException extends AdLoaderException {
}
