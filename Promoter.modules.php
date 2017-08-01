<?php
/**
 * ResourceLoader module definitions
 *
 * This file is part of the Promoter Extension to MediaWiki
 * https://www.mediawiki.org/wiki/Extension:Promoter
 *
 * @file
 * @ingroup Extensions
 *
 * @section LICENSE
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

global $wgResourceModules;

$promoterRemoteExtPath = 'WikiRights/Promoter/modules';
$promoterLocalBasePath = __DIR__ . '/modules';

// Register ResourceLoader modules
$wgResourceModules[ 'ext.promoter.adminUi' ] = [
	'localBasePath' => "$promoterLocalBasePath/ext.promoter.adminUi",
	'remoteExtPath' => "$promoterRemoteExtPath/ext.promoter.adminUi",
	'scripts'       => 'promoter.js',
	'styles'        => [
		'promoter.css',
		'adminui.common.css'
	],
	'messages'      => [
		'promoter-documentwrite-error',
		'promoter-close-title',
	]
];
$wgResourceModules[ 'ext.promoter.adminUi.adManager' ] = [
	'localBasePath' => "$promoterLocalBasePath/ext.promoter.adminUi.adManager",
	'remoteExtPath' => "$promoterRemoteExtPath/ext.promoter.adminUi.adManager",
	'dependencies' => [
		'ext.promoter.adminUi',
		'jquery.ui.dialog'
	],
	'scripts'       => 'admanager.js',
	'styles'        => 'admanager.css',
	'messages'      => [
		'promoter-add-ad-button',
		'promoter-add-ad-cancel-button',
		'promoter-archive-ad',
		'promoter-archive-ad-title',
		'promoter-archive-ad-confirm',
		'promoter-archive-ad-cancel',
		'promoter-add-new-ad-title',
		'promoter-delete-ad',
		'promoter-delete-ad-title',
		'promoter-delete-ad-confirm',
		'promoter-delete-ad-cancel',
	]
];
$wgResourceModules[ 'ext.promoter.adminUi.adEditor' ] = [
	'localBasePath' => $promoterLocalBasePath,
	'remoteExtPath' => $promoterRemoteExtPath,
	'dependencies' => [
		'ext.promoter.adminUi',
		'jquery.ui.dialog'
	],
	'scripts'       => 'ext.promoter.adminUi.adEditor/adeditor.js',
	'styles'        => 'ext.promoter.adminUi.adEditor/adeditor.css',
	'messages'      => [
		'promoter-clone',
		'promoter-clone-campaign',
		'promoter-clone-cancel',
		'promoter-archive-ad',
		'promoter-archive-ad-title',
		'promoter-archive-ad-confirm',
		'promoter-archive-ad-cancel',
		'promoter-delete-ad',
		'promoter-delete-ad-title',
		'promoter-delete-ad-confirm',
		'promoter-delete-ad-cancel',
	]
];

/*
$wgResourceModules[ 'ext.promoter.adStats' ] = array(
	'localBasePath' => $promoterLocalBasePath,
	'remoteExtPath' => $promoterRemoteExtPath,
	'scripts'       => 'ext.promoter.adStats/adStats.js',
);
*/


$wgResourceModules[ 'ext.promoter.adController' ] = [
	'localBasePath' => $promoterLocalBasePath . '/ext.promoter.adController',
	'remoteExtPath' => $promoterRemoteExtPath . '/ext.promoter.adController',
	'styles'        => 'adController.less',
	'scripts'       => 'adController.js',
	'dependencies'  => 'ext.googleUniversalAnalytics.utils',
	'position'      => 'bottom'
];


$wgResourceModules[ 'ext.promoter.adminUi.campaignManager' ] = [
	'localBasePath' => $promoterLocalBasePath,
	'remoteExtPath' => $promoterRemoteExtPath,
	'dependencies' => [
		'ext.promoter.adminUi',
		'jquery.ui.dialog',
		'jquery.ui.slider',
	],
	'scripts'       => 'ext.promoter.adminUi.campaignManager/campaignManager.js',
	'styles'        => 'ext.promoter.adminUi.campaignManager/campaignManager.css',
	'messages'      => []
];

$wgResourceModules[ 'jquery.owl-carousel' ] = [
	'localBasePath' => $promoterLocalBasePath,
	'remoteExtPath' => $promoterRemoteExtPath,
	'dependencies' => [],
	'scripts'       => 'jquery.owl-carousel/owl.carousel.js',
	'styles'        => [
		'jquery.owl-carousel/owl.carousel.css',
		'jquery.owl-carousel/owl.theme.default.css'
	],
	'messages'      => []
];


$wgResourceModules[ 'ext.promoter.gallery' ] = [
	'localBasePath' => $promoterLocalBasePath,
	'remoteExtPath' => $promoterRemoteExtPath,
	'dependencies' => [
		'jquery.owl-carousel',
		'jquery.equalizeCols',
		'ext.googleUniversalAnalytics.utils'
	],
	'scripts'       => 'ext.promoter.gallery/gallery.js',
	'styles'        => [
		'ext.promoter.gallery/gallery.less'
	],
	'position'      => 'top',
	'messages'      => []
];
