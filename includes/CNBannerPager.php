<?php

class CNBannerPager extends ReverseChronologicalPager {

	/** @var bool True if the form is to be created with editable elements */
	protected $editable = false;

	/** @var string Space separated strings to filter banner titles on */
	protected $filter = '';

	/** @var array HTMLFormFields to add to the results before every banner entry */
	protected $prependPrototypes = array();

	/** @var array HTMLFormFields to add to the results after every banner entry */
	protected $appendPrototypes = array();

	/** @var string 'Section' attribute to apply to the banner elements generated */
	protected $formSection = null;

	/**
	 * @param IContextSource $hostTitle
	 * @param string         $formSection
	 * @param array          $prependPrototypes
	 * @param array          $appendPrototypes
	 * @param string         $bannerFilter
	 * @param bool           $editable
	 */
	function __construct( $hostTitle, $formSection = null, $prependPrototypes = array(),
		$appendPrototypes = array(), $bannerFilter = '', $editable = false
	) {
		$this->editable = $editable;
		$this->filter = $bannerFilter;
		parent::__construct();

		$this->prependPrototypes = $prependPrototypes;
		$this->appendPrototypes = $appendPrototypes;
		$this->formSection = $formSection;

		$this->viewPage = $hostTitle;

		// Override paging defaults
		list( $this->mLimit, $this->mOffset ) = $this->mRequest->getLimitOffset( 20, '' );
		$this->mLimitsShown = array( 20, 50, 100 );

		// Get the database object
		$this->mDb = PRDatabase::getDb();
	}

	function getNavigationBar() {
		if ( isset( $this->mNavigationBar ) ) {
			return $this->mNavigationBar;
		}

		// Sets mNavigation bar with the default text which we will then wrap
		parent::getNavigationBar();

		$this->mNavigationBar = array(
			'class' => 'HTMLBannerPagerNavigation',
			'value' => $this->mNavigationBar
		);

		if ( $this->formSection ) {
			$this->mNavigationBar['section'] = $this->formSection;
		}

		return $this->mNavigationBar;
	}

	/**
	 * Set the database query to retrieve all the banners in the database
	 *
	 * @return array of query settings
	 */
	function getQueryInfo() {
		// When the filter comes in it is space delimited, so break that...
		$likeArray = preg_split( '/\s/', $this->filter );

		// ...and then insert all the wildcards between search terms
		if ( empty( $likeArray ) ) {
			$likeArray = $this->mDb->anyString();
		} else {
			$anyStringToken = $this->mDb->anyString();
			$tempArray = array( $anyStringToken );
			foreach ( $likeArray as $likePart ) {
				$tempArray[ ] = $likePart;
				$tempArray[ ] = $anyStringToken;
			}
			$likeArray = $tempArray;
		}

		return array(
			'tables' => array( 'templates' => 'cn_templates'),
			'fields' => array( 'templates.tmp_name', 'templates.tmp_id' ),
			'conds'  => array( 'templates.tmp_name' . $this->mDb->buildLike( $likeArray ) ),
		);
	}

	/**
	 * Sort the banner list by tmp_id (generally equals reverse chronological)
	 *
	 * @return string
	 */
	function getIndexField() {
		return 'templates.tmp_id';
	}

	/**
	 * Generate the contents of the table pager; intended to be consumed by the HTMLForm
	 *
	 * @param $row object: database row
	 *
	 * @return array HTMLFormElement classes
	 */
	function formatRow( $row ) {
		$retval = array();

		$bannerId = $row->tmp_id;
		$bannerName = $row->tmp_name;

		// Add the prepend prototypes
		foreach( $this->prependPrototypes as $prototypeName => $prototypeValues ) {
			$retval[ "{$prototypeName}-{$bannerName}" ] = $prototypeValues;
			if ( array_key_exists( 'id', $prototypeValues ) ) {
				$retval[ "{$prototypeName}-{$bannerId}" ][ 'id' ] .= "-$bannerName";
			}
		}

		// Now do the banner
		$retval["cn-banner-list-element-$bannerId"] = array(
			'class' => 'HTMLCentralNoticeBanner',
			'banner' => $bannerName,
			'withlabel' => true,
		);
		if ( $this->formSection ) {
			$retval["cn-banner-list-element-$bannerId"]['section'] = $this->formSection;
		}

		// Append prototypes
		foreach( $this->appendPrototypes as $prototypeName => $prototypeValues ) {
			$retval[ $prototypeName . "-$bannerId" ] = $prototypeValues;
			if ( array_key_exists( 'id', $prototypeValues ) ) {
				$retval[ $prototypeName . "-$bannerId" ][ 'id' ] .= "-$bannerId";
			}
		}

		// Set the disabled attribute
		if ( !$this->editable ) {
			foreach( $retval as $prototypeName => $prototypeValues ) {
				$retval[ $prototypeName ][ 'disabled' ] = true;
			}
		}

		return $retval;
	}

	/**
	 * Get the formatted result list. Calls getStartBody(), formatRow() and
	 * getEndBody(), concatenates the results and returns them.
	 *
	 * @return array
	 */
	public function getBody() {
		if ( !$this->mQueryDone ) {
			$this->doQuery();
		}

		if ( $this->mResult->numRows() ) {
			# Do any special query batches before display
			$this->doBatchLookups();
		}

		# Don't use any extra rows returned by the query
		$numRows = min( $this->mResult->numRows(), $this->mLimit );

		$retval = array();

		if ( $numRows ) {
			if ( $this->mIsBackwards ) {
				for ( $i = $numRows - 1; $i >= 0; $i-- ) {
					$this->mResult->seek( $i );
					$row = $this->mResult->fetchObject();
					$retval += $this->formatRow( $row );
				}
			} else {
				$this->mResult->seek( 0 );
				for ( $i = 0; $i < $numRows; $i++ ) {
					$row = $this->mResult->fetchObject();
					$retval += $this->formatRow( $row );
				}
			}
		} else {
			// TODO: empty value
		}
		return $retval;
	}
}

class HTMLBannerPagerNavigation extends HTMLFormField {
	/** Empty - no validation can be done on a navigation element */
	function validate( $value, $alldata ) { return true; }

	public function getInputHTML( $value ) {
		return $this->mParams['value'];
	}

	public function getDiv( $value ) {
		$html = Xml::openElement(
			'div',
			array( 'class' => "cn-banner-list-pager-nav", )
		);
		$html .= $this->getInputHTML( $value );
		$html .= Xml::closeElement( 'div' );

		return $html;
	}
}
