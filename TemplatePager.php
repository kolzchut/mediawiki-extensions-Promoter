<?php

/**
 * Provides pagination functionality for viewing banner lists in the CentralNotice admin interface.
 *
 * @deprecated 2.3 -- We're moving to an HTML form model and this is no longer used directly.
 * We still need to move the Campaign manager to HTMLForm though and so this still exists for
 * that part of CN.
 */
class TemplatePager extends ReverseChronologicalPager {
	var $onRemoveChange, $viewPage, $special;
	var $editable;
	var $filter;

	function __construct( $special, $filter = '' ) {
		$this->special = $special;
		$this->editable = $special->editable;
		$this->filter = $filter;
		parent::__construct();

		// Override paging defaults
		list( $this->mLimit, /* $offset */ ) = $this->mRequest->getLimitOffset( 20, '' );
		$this->mLimitsShown = array( 20, 50, 100 );

		$msg = Xml::encodeJsVar( $this->msg( 'promoter-confirm-delete' )->text() );
		$this->onRemoveChange = "if( this.checked ) { this.checked = confirm( $msg ) }";
		$this->viewPage = SpecialPage::getTitleFor( 'NoticeTemplate', 'view' );
	}

	/**
	 * Set the database query to retrieve all the banners in the database
	 *
	 * @return array of query settings
	 */
	function getQueryInfo() {
		$dbr = PRDatabase::getDb();

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

		return array(
			'tables' => array( 'templates' => 'cn_templates'),
			'fields' => array( 'templates.tmp_name', 'templates.tmp_id' ),
			'conds'  => array( 'templates.tmp_name' . $dbr->buildLike( $likeArray ) ),
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
	 * Generate the content of each table row (1 row = 1 banner)
	 *
	 * @param $row object: database row
	 *
	 * @return string HTML
	 */
	function formatRow( $row ) {

		// Begin banner row
		$htmlOut = Xml::openElement( 'tr' );

		if ( $this->editable ) {
			// Remove box
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
				Xml::check( 'removeTemplates[]', false,
					array(
						'value'    => $row->tmp_name,
						'onchange' => $this->onRemoveChange
					)
				)
			);
		}

		// Preview
		$banner = Banner::fromName( $row->tmp_name );
		$bannerRenderer = new BannerRenderer( $this->getContext(), $banner );

		$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
			$bannerRenderer->linkTo() . "<br>" . $bannerRenderer->previewFieldSet()
		);

		// End banner row
		$htmlOut .= Xml::closeElement( 'tr' );

		return $htmlOut;
	}

	/**
	 * Specify table headers
	 *
	 * @return string HTML
	 */
	function getStartBody() {
		$htmlOut = '';
		$htmlOut .= Xml::openElement( 'table', array( 'cellpadding' => 9 ) );
		$htmlOut .= Xml::openElement( 'tr' );
		if ( $this->editable ) {
			$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'width' => '5%' ),
				$this->msg( 'promoter-remove' )->text()
			);
		}
		$htmlOut .= Xml::element( 'th', array( 'align' => 'left' ),
			$this->msg( 'promoter-templates' )->text()
		);
		$htmlOut .= Xml::closeElement( 'tr' );
		return $htmlOut;
	}

	/**
	 * Close table and add Submit button
	 *
	 * @return string HTML
	 */
	function getEndBody() {
		$htmlOut = '';
		$htmlOut .= Xml::closeElement( 'table' );
		if ( $this->editable ) {
			$htmlOut .= Html::hidden( 'authtoken', $this->getUser()->getEditToken() );
			$htmlOut .= Xml::tags( 'div',
				array( 'class' => 'cn-buttons' ),
				Xml::submitButton( $this->msg( 'promoter-modify' )->text() )
			);
		}
		return $htmlOut;
	}
}
