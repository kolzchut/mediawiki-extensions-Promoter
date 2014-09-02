# Promoter - extension semi-documentation

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
1. Add Google Analytics support to ads:
	* Notify about the current page, campaign and ad selected
	* Notify about clicking an ad's main link. Probably not as an event, but by supplying the information in the
	  link using _utm variables.
	* Ask Nir (from Google) how to link both types of data (so we compare impressions/clicks)
1. On an ad page, show what campaigns it is linked to.
1. Make ad previews work
1. Being able to rename an ad would be nice...
1. If an ad has a mainlink to the page the user is on, filter it out?


## Changelog
- 01-Sep-2014 Option to disable the fallback campaign (through the UI)
