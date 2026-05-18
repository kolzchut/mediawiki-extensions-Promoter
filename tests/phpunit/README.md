# Promoter Extension Tests

This directory contains automated tests for the Promoter extension's
defense-in-depth permission checks on the `Ad` and `AdCampaign` model classes.

## Layout

```
tests/phpunit/
├── unit/
│   ├── AdPermissionsTest.php            -- Ad write methods, denial paths
│   └── AdCampaignPermissionsTest.php    -- AdCampaign methods that take a
│                                           User parameter, denial paths
└── integration/
    ├── AdPermissionsIntegrationTest.php       -- Ad write methods,
    │                                             authorized + denial paths
    │                                             with DB state assertions
    └── AdCampaignPermissionsIntegrationTest.php
                                                -- AdCampaign write methods,
                                                   including the ones that
                                                   pull the user from
                                                   RequestContext
```

## What each layer covers

### Unit tests (`MediaWikiUnitTestCase`)

The permission check in every Promoter write method runs *before* any
database or `MediaWikiServices` access, so the **denial path is safe to
exercise without bootstrapping MediaWiki**. Unit tests cover that path
with mocked `User` objects — fast, deterministic, no DB.

Unit tests do **not** cover the authorized "happy path" because the
work afterwards needs the service container, primary DB, etc.

### Integration tests (`MediaWikiIntegrationTestCase`, `@group Database`)

Integration tests cover:

- The **authorized** flow: create a user with `promoter-admin`,
  perform the write, and read state back from the DB to confirm.
- The **unauthorized** flow against the real stack: confirms that the
  permission check still fires and, critically, that **no DB rows are
  written, modified, or deleted** when permission is denied.
- The `RequestContext`-based methods (`setBooleanCampaignSetting`,
  `setNumericCampaignSetting`, `addAdTo`, `removeAdFor`,
  `addAdToCampaigns`, `removeAdForCampaigns`) — these don't take a
  `User` parameter, so the test installs the test user via
  `RequestContext::getMain()->setUser( $user )`.

## Methods covered

### `Ad`

| Method     | Unit (denial) | Integration (auth + denial) |
|------------|:-------------:|:---------------------------:|
| `save`     | ✓             | ✓                           |
| `addAd`    | ✓             | ✓                           |
| `removeAd` | ✓             | ✓                           |
| `cloneAd`  | ✓             | ✓                           |

### `AdCampaign`

| Method                       | Unit (denial)       | Integration                  |
|------------------------------|:-------------------:|:----------------------------:|
| `addCampaign`                | ✓                   | ✓ (auth + denial)            |
| `removeCampaign`             | ✓                   | ✓ (auth + denial)            |
| `setBooleanCampaignSetting`  | — *(uses Context)*  | ✓ (auth + denial)            |
| `setNumericCampaignSetting`  | — *(uses Context)*  | ✓ (denial)                   |
| `addAdTo`                    | — *(uses Context)*  | ✓ (auth + denial)            |
| `removeAdFor`                | — *(uses Context)*  | ✓ (denial)                   |
| `addAdToCampaigns`           | — *(uses Context)*  | ✓ (denial)                   |
| `removeAdForCampaigns`       | — *(uses Context)*  | ✓ (denial)                   |

## Running the tests

From the MediaWiki core directory:

```bash
# All Promoter tests
php tests/phpunit/phpunit.php extensions/WikiRights/Promoter/tests/

# Unit only (fast — no DB)
php tests/phpunit/phpunit.php extensions/WikiRights/Promoter/tests/phpunit/unit/

# Integration only (needs MW test DB)
php tests/phpunit/phpunit.php extensions/WikiRights/Promoter/tests/phpunit/integration/

# A single file
php tests/phpunit/phpunit.php extensions/WikiRights/Promoter/tests/phpunit/unit/AdPermissionsTest.php
```

Inside the Kol-Zchut dev container, run from `/var/www/html/w`:

```bash
./dev.sh exec-main -- php tests/phpunit/phpunit.php \
    extensions/WikiRights/Promoter/tests/
```

## Special pages

The Special pages (`SpecialPromoter`, `SpecialPromoterAds`) simply
delegate writes to the model layer covered here, so their permission
behaviour is verified indirectly. Driving the Special pages end-to-end
(form submission, OutputPage capture) was deliberately out of scope —
the model-layer tests already prove that an unauthorized request
cannot mutate state, regardless of which UI path led to the call.

## Design notes

- Each integration test seeds its own data, asserts the outcome,
  and lets `MediaWikiIntegrationTestCase` truncate the relevant
  tables (`pr_ads`, `pr_campaigns`, `pr_adlinks`, `pr_ad_log`) on
  teardown via `$this->tablesUsed`.
- Permissions are granted with `overrideUserPermissions()` rather
  than by editing user groups. This keeps tests independent of the
  surrounding `$wgGroupPermissions` configuration.
- All denial assertions check `$e->permission === 'promoter-admin'`
  rather than just `expectException( PermissionsError::class )`,
  so a regression that throws `PermissionsError` for the wrong
  right (e.g. a copy-paste mistake) would still be caught.
