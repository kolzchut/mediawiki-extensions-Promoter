import { test, expect } from '@playwright/test';
import { PromoterAdsPage } from '../pages/PromoterAdsPage';
import { isIgnorablePageError } from '../pages/mwAuth';

/**
 * Promoter admin UI — Special:PromoterAds.
 *
 * Regression cover for the MW 1.43 modernization:
 *   - The page renders at all (it previously fataled on removed 1.43 APIs
 *     `WebRequest::getLimitOffset()` and `Sanitizer::escapeId()`).
 *   - The admin dialogs are OOUI (`OO.ui.prompt` / `OO.ui.confirm`), not the
 *     old jQuery UI `.dialog()` widgets — asserted structurally so the checks
 *     don't depend on the wiki content language.
 *
 * The dialogs only render for a logged-in `promoter-admin` user, so the pack
 * skips cleanly (never silently passes) when creds are absent.
 *
 * Run (against local dev):
 *   MW_USERNAME=Dockeradmin MW_PASSWORD=... \
 *     npx playwright test e2e/promoter-admin-dialogs.spec.ts
 */

const USERNAME = process.env.MW_USERNAME ?? '';
const PASSWORD = process.env.MW_PASSWORD ?? '';

test.describe('Promoter admin dialogs (Special:PromoterAds)', () => {
  test.skip(
    !USERNAME || !PASSWORD,
    'Set MW_USERNAME / MW_PASSWORD (a promoter-admin user, e.g. Dockeradmin) to run these specs.'
  );

  let pageErrors: string[];

  test.beforeEach(async ({ page }) => {
    // Fail loudly on any uncaught page error (a broken module or a PHP-rendered
    // fatal would surface here) — except third-party reCAPTCHA errors, which
    // come from staging's login-widget site config, not the code under test.
    pageErrors = [];
    page.on('pageerror', (err) => {
      const message = String(err);
      if (isIgnorablePageError(message)) return;
      pageErrors.push(message);
    });

    const promoter = new PromoterAdsPage(page);
    await promoter.login(USERNAME, PASSWORD);
    await promoter.gotoAdManager();
  });

  test('ad-manager page renders without a 1.43 fatal', async ({ page }) => {
    // The removed-API fatals produced a MediaWiki "Internal error" page.
    await expect(page.locator('body')).not.toContainText('Internal error');
    await expect(page.locator('body')).not.toContainText('Fatal error');
    // The bulk-manage controls only exist once the ad list has rendered.
    await expect(new PromoterAdsPage(page).addAdButton()).toBeVisible();
    expect(pageErrors, `unexpected page errors: ${pageErrors.join('; ')}`).toHaveLength(0);
  });

  test('Add-ad opens an OOUI prompt dialog (name input)', async ({ page }) => {
    const promoter = new PromoterAdsPage(page);

    await promoter.addAdButton().click();

    const dialog = promoter.openDialog();
    await expect(dialog).toBeVisible();
    // OO.ui.prompt = a dialog carrying a text input plus action buttons.
    await expect(dialog.getByRole('textbox')).toBeVisible();
    await expect(dialog.getByRole('button')).not.toHaveCount(0);

    // Close without creating anything.
    await promoter.dismissDialog();
    expect(pageErrors, `unexpected page errors: ${pageErrors.join('; ')}`).toHaveLength(0);
  });

  test('Removing selected ads opens an OOUI confirm dialog (no text input) and cancels safely', async ({
    page,
  }) => {
    const promoter = new PromoterAdsPage(page);

    const firstCheckbox = promoter.adCheckboxes().first();
    await expect(firstCheckbox, 'dev target has at least one ad to select').toBeVisible();
    await firstCheckbox.check();

    // Selecting an ad enables the remove button (the `this.checked` handler).
    const removeButton = promoter.removeSelectedButton();
    await expect(removeButton).toBeEnabled();
    await removeButton.click();

    const dialog = promoter.openDialog();
    await expect(dialog).toBeVisible();
    // OO.ui.confirm = a dialog with action buttons and NO text input.
    await expect(dialog.getByRole('button')).not.toHaveCount(0);
    await expect(dialog.getByRole('textbox')).toHaveCount(0);

    // Cancel — must NOT delete the ad.
    await promoter.dismissDialog();

    // The ad list is unchanged (still on the ad manager, checkbox still present).
    await expect(promoter.adCheckboxes().first()).toBeVisible();
    expect(pageErrors, `unexpected page errors: ${pageErrors.join('; ')}`).toHaveLength(0);
  });
});
