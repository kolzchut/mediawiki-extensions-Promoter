<?php

/**
 * Provides the criteria needed to do allocation.
 */
class AllocationContext {
	protected $anonymous;


	/**
	 * All criteria are required for ad requests, but when AllocationContext's
	 * are created internally, some criteria may be null to allow special filtering.
	 *
	 * @param boolean $anonymous
	 */
	function __construct( $anonymous ) {
		$this->anonymous = $anonymous;
	}

	function getAnonymous() {
		return $this->anonymous;
	}

}
