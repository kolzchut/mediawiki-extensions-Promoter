# Promoter - extension semi-documentation

## How to use

### Ad carousel
Using a <promotergallery /> tag, you can display an ad carousel/slider, which tries to load all
ads for the current page.

## Things you should know:
- A non-existant campaign fallbacks to a default campaign ($wgPromoterFallbackCampaign)
- A disabled (inactive) campaign is the same as a non-existant one
- An empty campaign prevents falling back to default

## Misc
- If you would like to use non-ansi names for ads, you must enable MediaWiki's $wgExperimentalHtmlIds (set it to 'true');



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
