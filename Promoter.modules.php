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
$wgResourceModules[ 'jquery.ui.multiselect' ] = array(
	'localBasePath' => "$promoterLocalBasePath/jquery.ui.multiselect",
	'remoteExtPath' => "$promoterRemoteExtPath/jquery.ui.multiselect",
	'dependencies'  => array(
		'jquery.ui.core',
		'jquery.ui.sortable',
		'jquery.ui.draggable',
		'jquery.ui.droppable',
		'mediawiki.jqueryMsg'
	),
	'scripts'       => 'ui.multiselect.js',
	'styles'        => 'ui.multiselect.css',
);
$wgResourceModules[ 'ext.promoter.adminUi' ] = array(
	'localBasePath' => "$promoterLocalBasePath/ext.promoter.adminUi",
	'remoteExtPath' => "$promoterRemoteExtPath/ext.promoter.adminUi",
	'dependencies' => array(
		'jquery.ui.datepicker',
		'jquery.ui.multiselect'
	),
	'scripts'       => 'promoter.js',
	'styles'        => array(
		'promoter.css',
		'adminui.common.css'
	),
	'messages'      => array(
		'promoter-documentwrite-error',
		'promoter-close-title',
		'promoter-select-all',
		'promoter-remove-all',
		'promoter-items-selected'
	)
);
$wgResourceModules[ 'ext.promoter.adminUi.adManager' ] = array(
	'localBasePath' => "$promoterLocalBasePath/ext.promoter.adminUi.adManager",
	'remoteExtPath' => "$promoterRemoteExtPath/ext.promoter.adminUi.adManager",
	'dependencies' => array(
		'ext.promoter.adminUi',
		'jquery.ui.dialog'
	),
	'scripts'       => 'admanager.js',
	'styles'        => 'admanager.css',
	'messages'      => array(
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
	)
);
$wgResourceModules[ 'ext.promoter.adminUi.adEditor' ] = array(
	'localBasePath' => $promoterLocalBasePath,
	'remoteExtPath' => $promoterRemoteExtPath,
	'dependencies' => array(
		'ext.promoter.adminUi',
		'jquery.ui.dialog'
	),
	'scripts'       => 'ext.promoter.adminUi.adEditor/adeditor.js',
	'styles'        => 'ext.promoter.adminUi.adEditor/adeditor.css',
	'messages'      => array(
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
	)
);

/*
$wgResourceModules[ 'ext.promoter.adStats' ] = array(
	'localBasePath' => $promoterLocalBasePath,
	'remoteExtPath' => $promoterRemoteExtPath,
	'scripts'       => 'ext.promoter.adStats/adStats.js',
);
*/


$wgResourceModules[ 'ext.promoter.adController' ] = array(
	'localBasePath' => $promoterLocalBasePath . '/ext.promoter.adController',
	'remoteExtPath' => $promoterRemoteExtPath . '/ext.promoter.adController',
	'styles'        => 'adController.less',
	'scripts'       => 'adController.js',
	'position'      => 'bottom'
);


$wgResourceModules[ 'ext.promoter.adminUi.campaignManager' ] = array(
	'localBasePath' => $promoterLocalBasePath,
	'remoteExtPath' => $promoterRemoteExtPath,
	'dependencies' => array(
		'ext.promoter.adminUi',
		'jquery.ui.dialog',
		'jquery.ui.slider',
	),
	'scripts'       => 'ext.promoter.adminUi.campaignManager/campaignManager.js',
	'styles'        => 'ext.promoter.adminUi.campaignManager/campaignManager.css',
	'messages'      => array( )
);

$wgResourceModules[ 'jquery.owl-carousel' ] = array(
	'localBasePath' => $promoterLocalBasePath,
	'remoteExtPath' => $promoterRemoteExtPath,
	'dependencies' => array( ),
	'scripts'       => 'jquery.owl-carousel/owl.carousel.js',
	'styles'        => array(
		'jquery.owl-carousel/owl.carousel.css',
		'jquery.owl-carousel/owl.theme.default.css'
	),
	'messages'      => array( )
);


$wgResourceModules[ 'ext.promoter.gallery' ] = array(
	'localBasePath' => $promoterLocalBasePath,
	'remoteExtPath' => $promoterRemoteExtPath,
	'dependencies' => array(
		'jquery.owl-carousel',
		'jquery.equalizeCols'
	),
	'scripts'       => 'ext.promoter.gallery/gallery.js',
	'styles'        => array(
		'ext.promoter.gallery/gallery.less'
	),
	'position'      => 'top',
	'messages'      => array( )
);
