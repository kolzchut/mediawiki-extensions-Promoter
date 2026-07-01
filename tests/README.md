# Promoter e2e tests

Playwright end-to-end tests for the Promoter admin UI, focused on
`Special:PromoterAds` and its OOUI dialogs. These lock in the MW 1.43
modernization: that the ad-manager page renders (it previously fataled on the
removed `WebRequest::getLimitOffset()` / `Sanitizer::escapeId()` APIs), and that
the add/remove dialogs are OOUI (`OO.ui.prompt` / `OO.ui.confirm`) rather than
the old jQuery UI `.dialog()` widgets.

Assertions are **structural** (element ids, ARIA roles, OOUI CSS classes), not
button text, so they are independent of the wiki's content language.

## Setup

```sh
cd tests
npm install
npx playwright install chromium
```

## Run

The dialogs require a logged-in user with the `promoter-admin` right, so the
specs **skip** (they do not silently pass) unless credentials are supplied.

```sh
# against local dev (main site, he)
MW_USERNAME=Dockeradmin MW_PASSWORD=<dev-password> \
  npx playwright test

# against another target
MW_BASE_URL=https://staging-test.wikirights.org.il MW_SCRIPT_PATH=/w/he \
MW_USERNAME=Dockeradmin MW_PASSWORD=<vault-password> \
  npx playwright test
```

## Environment variables

| Variable         | Default                 | Purpose                                  |
|------------------|-------------------------|------------------------------------------|
| `MW_BASE_URL`    | `http://localhost:8082` | Wiki base URL                            |
| `MW_SCRIPT_PATH` | `/he`                   | Language/path prefix to the wiki         |
| `MW_USERNAME`    | — (skips if unset)      | A `promoter-admin` user (e.g. Dockeradmin) |
| `MW_PASSWORD`    | — (skips if unset)      | That user's password                     |

The remove-ads spec selects an ad and opens the confirm dialog but always
**cancels** — it never deletes anything.
