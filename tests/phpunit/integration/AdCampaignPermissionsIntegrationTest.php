<?php

namespace MediaWiki\Extension\Promoter\Tests\Integration;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Promoter\AdCampaign;
use MediaWikiIntegrationTestCase;
use PermissionsError;
use User;

/**
 * Full-stack permission tests for AdCampaign write methods.
 *
 * This file covers:
 *   - addCampaign / removeCampaign (User passed explicitly): authorized
 *     success + unauthorized denial with DB state unchanged.
 *   - setBooleanCampaignSetting / setNumericCampaignSetting / addAdTo /
 *     removeAdFor / addAdToCampaigns / removeAdForCampaigns (user pulled
 *     from RequestContext::getMain()): denial + (where applicable) success,
 *     injecting the user via RequestContext::getMain()->setUser().
 *
 * @group Database
 * @covers \MediaWiki\Extension\Promoter\AdCampaign
 */
class AdCampaignPermissionsIntegrationTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->tablesUsed = array_merge(
			$this->tablesUsed ?? [],
			[ 'pr_campaigns', 'pr_adlinks', 'pr_ads' ]
		);
	}

	private function makeUser( bool $admin ): User {
		$user = $this->getMutableTestUser()->getUser();
		$this->overrideUserPermissions(
			$user,
			$admin ? [ 'promoter-admin', 'edit' ] : [ 'edit' ]
		);
		return $user;
	}

	/**
	 * Install $user as the user returned by RequestContext::getMain()->getUser().
	 * Used by AdCampaign methods that don't take a User parameter.
	 */
	private function setContextUser( User $user ): void {
		RequestContext::getMain()->setUser( $user );
	}

	private function countCampaigns( string $name ): int {
		return (int)$this->getDb()->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'pr_campaigns' )
			->where( [ 'cmp_name' => $name ] )
			->caller( __METHOD__ )
			->fetchField();
	}

	private function countAdlinks( int $cmpId, int $adId ): int {
		return (int)$this->getDb()->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'pr_adlinks' )
			->where( [ 'cmp_id' => $cmpId, 'ad_id' => $adId ] )
			->caller( __METHOD__ )
			->fetchField();
	}

	private function insertTestAd( string $name ): int {
		$this->getDb()->insert( 'pr_ads',
			[ 'ad_name' => $name, 'ad_display_anon' => 1, 'ad_display_user' => 1 ],
			__METHOD__
		);
		return (int)$this->getDb()->insertId();
	}

	// addCampaign / removeCampaign -- accept User param.

	/**
	 * @covers \MediaWiki\Extension\Promoter\AdCampaign::addCampaign
	 */
	public function testAddCampaignSucceedsForAuthorizedUser(): void {
		$result = AdCampaign::addCampaign( 'CmpA', 1, $this->makeUser( true ) );
		$this->assertIsInt( $result );
		$this->assertSame( 1, $this->countCampaigns( 'CmpA' ) );
	}

	/**
	 * @covers \MediaWiki\Extension\Promoter\AdCampaign::addCampaign
	 */
	public function testAddCampaignBlockedForUnauthorizedUser(): void {
		try {
			AdCampaign::addCampaign( 'CmpDenied', 1, $this->makeUser( false ) );
			$this->fail( 'Expected PermissionsError was not thrown' );
		} catch ( PermissionsError $e ) {
			// Exception class is enough; PermissionsError::$permission was deprecated in MW 1.43.
		}
		$this->assertSame( 0, $this->countCampaigns( 'CmpDenied' ) );
	}

	/**
	 * @covers \MediaWiki\Extension\Promoter\AdCampaign::removeCampaign
	 */
	public function testRemoveCampaignSucceedsForAuthorizedUser(): void {
		$admin = $this->makeUser( true );
		AdCampaign::addCampaign( 'CmpToRemove', 1, $admin );
		$this->assertSame( 1, $this->countCampaigns( 'CmpToRemove' ) );

		$this->assertSame( true, AdCampaign::removeCampaign( 'CmpToRemove', $admin ) );
		$this->assertSame( 0, $this->countCampaigns( 'CmpToRemove' ) );
	}

	/**
	 * @covers \MediaWiki\Extension\Promoter\AdCampaign::removeCampaign
	 */
	public function testRemoveCampaignBlockedForUnauthorizedUser(): void {
		AdCampaign::addCampaign( 'CmpProtected', 1, $this->makeUser( true ) );

		try {
			AdCampaign::removeCampaign( 'CmpProtected', $this->makeUser( false ) );
			$this->fail( 'Expected PermissionsError was not thrown' );
		} catch ( PermissionsError $e ) {
			// Exception class is enough; PermissionsError::$permission was deprecated in MW 1.43.
		}

		$this->assertSame( 1, $this->countCampaigns( 'CmpProtected' ),
			'Campaign should still exist after blocked removal' );
	}

	// RequestContext-based methods.

	/**
	 * @covers \MediaWiki\Extension\Promoter\AdCampaign::setBooleanCampaignSetting
	 */
	public function testSetBooleanCampaignSettingBlockedForUnauthorizedUser(): void {
		AdCampaign::addCampaign( 'CmpBool', 1, $this->makeUser( true ) );

		$this->setContextUser( $this->makeUser( false ) );
		$this->expectException( PermissionsError::class );
		AdCampaign::setBooleanCampaignSetting( 'CmpBool', 'enabled', 0 );
	}

	/**
	 * @covers \MediaWiki\Extension\Promoter\AdCampaign::setBooleanCampaignSetting
	 */
	public function testSetBooleanCampaignSettingSucceedsForAuthorizedUser(): void {
		$admin = $this->makeUser( true );
		AdCampaign::addCampaign( 'CmpBool2', 1, $admin );
		$this->setContextUser( $admin );

		AdCampaign::setBooleanCampaignSetting( 'CmpBool2', 'enabled', 0 );

		$settings = AdCampaign::getCampaignSettings( 'CmpBool2' );
		$this->assertSame( '0', (string)$settings['enabled'] );
	}

	/**
	 * @covers \MediaWiki\Extension\Promoter\AdCampaign::setNumericCampaignSetting
	 */
	public function testSetNumericCampaignSettingBlockedForUnauthorizedUser(): void {
		AdCampaign::addCampaign( 'CmpNum', 1, $this->makeUser( true ) );

		$this->setContextUser( $this->makeUser( false ) );
		$this->expectException( PermissionsError::class );
		AdCampaign::setNumericCampaignSetting( 'CmpNum', 'enabled', 1 );
	}

	/**
	 * @covers \MediaWiki\Extension\Promoter\AdCampaign::addAdTo
	 */
	public function testAddAdToBlockedForUnauthorizedUser(): void {
		$admin = $this->makeUser( true );
		$cmpId = AdCampaign::addCampaign( 'CmpLink', 1, $admin );
		$adId = $this->insertTestAd( 'LinkAd' );

		$this->setContextUser( $this->makeUser( false ) );
		try {
			AdCampaign::addAdTo( 'CmpLink', $adId );
			$this->fail( 'Expected PermissionsError was not thrown' );
		} catch ( PermissionsError $e ) {
			// Exception class is enough; PermissionsError::$permission was deprecated in MW 1.43.
		}
		$this->assertSame( 0, $this->countAdlinks( (int)$cmpId, $adId ),
			'No adlink should have been created when caller is unauthorized' );
	}

	/**
	 * @covers \MediaWiki\Extension\Promoter\AdCampaign::addAdTo
	 */
	public function testAddAdToSucceedsForAuthorizedUser(): void {
		$admin = $this->makeUser( true );
		$cmpId = AdCampaign::addCampaign( 'CmpLink2', 1, $admin );
		$adId = $this->insertTestAd( 'LinkAd2' );

		$this->setContextUser( $admin );
		$this->assertSame( true, AdCampaign::addAdTo( 'CmpLink2', $adId ) );
		$this->assertSame( 1, $this->countAdlinks( (int)$cmpId, $adId ) );
	}

	/**
	 * @covers \MediaWiki\Extension\Promoter\AdCampaign::removeAdFor
	 */
	public function testRemoveAdForBlockedForUnauthorizedUser(): void {
		$admin = $this->makeUser( true );
		$cmpId = AdCampaign::addCampaign( 'CmpUnlink', 1, $admin );
		$adId = $this->insertTestAd( 'UnlinkAd' );
		$this->setContextUser( $admin );
		AdCampaign::addAdTo( 'CmpUnlink', $adId );
		$this->assertSame( 1, $this->countAdlinks( (int)$cmpId, $adId ) );

		$this->setContextUser( $this->makeUser( false ) );
		try {
			AdCampaign::removeAdFor( 'CmpUnlink', $adId );
			$this->fail( 'Expected PermissionsError was not thrown' );
		} catch ( PermissionsError $e ) {
			// Exception class is enough; PermissionsError::$permission was deprecated in MW 1.43.
		}
		$this->assertSame( 1, $this->countAdlinks( (int)$cmpId, $adId ),
			'Adlink should remain after blocked unlink attempt' );
	}

	/**
	 * @covers \MediaWiki\Extension\Promoter\AdCampaign::addAdToCampaigns
	 */
	public function testAddAdToCampaignsBlockedForUnauthorizedUser(): void {
		$admin = $this->makeUser( true );
		$c1 = (int)AdCampaign::addCampaign( 'CmpMulti1', 1, $admin );
		$c2 = (int)AdCampaign::addCampaign( 'CmpMulti2', 1, $admin );
		$adId = $this->insertTestAd( 'MultiAd' );

		$this->setContextUser( $this->makeUser( false ) );
		try {
			AdCampaign::addAdToCampaigns( [ $c1, $c2 ], $adId );
			$this->fail( 'Expected PermissionsError was not thrown' );
		} catch ( PermissionsError $e ) {
			// Exception class is enough; PermissionsError::$permission was deprecated in MW 1.43.
		}
		$this->assertSame( 0, $this->countAdlinks( $c1, $adId ) );
		$this->assertSame( 0, $this->countAdlinks( $c2, $adId ) );
	}

	/**
	 * @covers \MediaWiki\Extension\Promoter\AdCampaign::removeAdForCampaigns
	 */
	public function testRemoveAdForCampaignsBlockedForUnauthorizedUser(): void {
		$admin = $this->makeUser( true );
		$c1 = (int)AdCampaign::addCampaign( 'CmpMultiR1', 1, $admin );
		$c2 = (int)AdCampaign::addCampaign( 'CmpMultiR2', 1, $admin );
		$adId = $this->insertTestAd( 'MultiAdR' );
		$this->setContextUser( $admin );
		AdCampaign::addAdToCampaigns( [ $c1, $c2 ], $adId );

		$this->setContextUser( $this->makeUser( false ) );
		try {
			AdCampaign::removeAdForCampaigns( [ $c1, $c2 ], $adId );
			$this->fail( 'Expected PermissionsError was not thrown' );
		} catch ( PermissionsError $e ) {
			// Exception class is enough; PermissionsError::$permission was deprecated in MW 1.43.
		}
		$this->assertSame( 1, $this->countAdlinks( $c1, $adId ),
			'Adlink should remain on campaign 1 after blocked unlink' );
		$this->assertSame( 1, $this->countAdlinks( $c2, $adId ),
			'Adlink should remain on campaign 2 after blocked unlink' );
	}
}
