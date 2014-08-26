# Promoter - extension semi-documentation

## Things you should know:
- A non-existant campaign fallbacks to a default campaign ($wgPromoterFallbackCampaign)
- A disabled (inactive) campaign is the same as a non-existant one
- An empty campaign prevents falling back to default
- The fallback campaign cannot be disabled. The flag will have no effect.

## Misc
- If you would like to use non-ansi names for ads, you must enable MediaWiki's $wgExperimentalHtmlIds (set it to 'true');



## Todo
1. Currently there is no check for page-specific campaigns, only category-related ones;
  therefore, there is also no way to specify which of the two the campaign is targeting
  (as we could have a page and category of the same name).
2. Make ad previews work
3. Being able to rename an ad would be nice...
4. Have spaces in ad names (probably need automatic conversion " "->"_", like with wikipages.
5. Add Google Analytics support to ads:
	* Notify about the current page, campaign and ad selected
	* Notify about clicking an ad's main link. Probably not as an event, but by supplying the information in the
	  link using _utm variables.
