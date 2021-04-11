# Promoter - extension semi-documentation

This extension is currently unstable; we have started changing it to be
used with our extension Discovery, so that Promoter only handles creating
campaigns and ads, but Discovery is in charge of display logic, etc.
The plan is for the two extensions to merge eventually.

## Configuration
- $wgPromoterFallbackCampaign - A non-existant campaign will fall backs
  to this default campaign. By default, this is set to 'general'.

### User rights
Anyone can access the special pages, but the 'promoter-admin' user right
is required to edit anything in them.

## How to use
Basically, this extension cannot be directly used anymore; it only offers an internal PHP API to
request ads, which is used in turn by Extension:Discovery, which offers its own MediaWiki API
(and consumes it as well).

## Things you should know:
- A disabled (inactive) campaign is the same as a non-existant one
- An empty campaign prevents falling back to default, so it is possible
  to prevent any ads for a category

## Todo
1. Option to rename campaign (critical!)
1. Option to delete a campaign (where did it go?)
1. Currently, there is no option for page-specific campaigns, only category-related ones;
  therefore, there is also no way to specify which of the two the campaign is targeting
  (as we could have a page and category of the same name).
1. Have spaces in ad names (probably need automatic conversion " "->"_", like with wikipages)
1. On an ad page, show what campaigns it is linked to.
1. Make ad previews work
1. Being able to rename an ad would be nice...
1. If an ad has a mainlink to the page the user is on, filter it out? [low priority]


## Changelog
- 1.0.0, 2021-04-12
  - A major milestone on the road to merging this extension with extension:Discovery:
	- Remove all logic from this extension, as it no longer handles choosing and displaying ads
    - This includes the gallery widget and the entire adcontroller, and generally most code
  - Convert to extension registration
  - Make the extension compatible with MediaWiki 1.35
  -	Finally, give it a proper version number
- 2018-08-28
  * Add configuration $wgPromoterShowAds
  * Change the extension for use with extension:Discovery
- 2017-08-07
  * Upgrade owl-carousel
- 2016-02-29
  * Allow disabling view and/or click tracking ($wgPromoterTrackAds)
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
