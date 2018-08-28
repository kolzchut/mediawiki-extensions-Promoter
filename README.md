# Promoter - extension semi-documentation

This extension is currently unstable; we have started changing it to be
used with our extension Discovery, so that Promoter only handles creating
campaigns and ads, but Discovery is in charge of display logic, etc.
The plan is for the two extensions to merge eventually.

## Configuration
- $wgPromoterShowAds - turn off the automatic showing of ads in the
  sidebar. This is mainly useful when Promoter is used together with
  extension:Discovery.
- $wgPromoterFallbackCampaign - A non-existant campaign will fall backs
  to this default campaign. By default this is set to 'general'.
- $wgPromoterTrackAds - disable view and/or click tracking. Possible
  values: true/false/`array( 'view' => true, 'click' => true )`
- $wgPromoterAdMaxAge - how much time to cache ads on the server-side
  (in seconds). Default is 600 seconds.

### User rights
Anyone can access the special pages, but the 'promoter-admin' user right
is required to edit anything in them.

## How to use

### Ad carousel
Using a <promotergallery /> tag, you can display an ad carousel/slider,
which tries to load all ads for the current page.

## Things you should know:
- A disabled (inactive) campaign is the same as a non-existant one
- An empty campaign prevents falling back to default, so it is possible
  to prevent any ads for a category

## Misc
- If you would like to use non-ansi names for ads, you must enable
  MediaWiki's $wgExperimentalHtmlIds (set it to 'true').


## Todo
1. Option to rename campaign (critical!)
1. Option to delete campaign (where did it go?)
1. Currently there is no option for page-specific campaigns, only category-related ones;
  therefore, there is also no way to specify which of the two the campaign is targeting
  (as we could have a page and category of the same name).
1. Have spaces in ad names (probably need automatic conversion " "->"_", like with wikipages.
1. On an ad page, show what campaigns it is linked to.
1. Make ad previews work
1. Being able to rename an ad would be nice...
1. If an ad has a mainlink to the page the user is on, filter it out? [low priority]


## Changelog
- 2018-08-28
  * Add configuration $wgPromoterShowAds
  * Change the extension for use with extension:Discovery
- 2017-08-07
  * Upgrade owl-carousel
- 2016-02-29
  * Allow to disable view and/or click tracking ($wgPromoterTrackAds)
  * Improve documentation somewhat
- 2016-01-20 Depened on Extension:GoogleUniversalAnalytics (using RL module) for event tracking
- 2015-02-09 Update design of sidebar ads to make them a bit more noticeable
- 2014-11-17 Parse ad messages as wikitext
- 2014-10-30
	* Add a new ad carousel-slider for use on the main page
	* Allow to preview a specific ad
- 2014-09-15 Track ads using Google Analytics events:
	* 'ad-impressions' notifies about the current page, campaign and ad selected
	* 'ad-clicks' notifies about clicking an ad's main link - sends campaign and ad
- 2014-09-01 Allow to disable the fallback campaign (through the UI)
- 2014-08-26 Initial MVP for Promoter
- 2014-05-14 Fork from Extension:CentralNotice and start demolition
