import { Page, Locator, expect } from '@playwright/test';

/**
 * Page object for Special:PromoterAds (the ad-manager admin UI) and its
 * OOUI dialogs.
 *
 * Locators are deliberately structural (element ids, ARIA roles, OOUI CSS
 * classes) rather than localized button text, so the suite is not tied to
 * the wiki's content language.
 */
export class PromoterAdsPage {
  readonly page: Page;
  readonly scriptPath: string;

  constructor(page: Page) {
    this.page = page;
    // e.g. "/he" — the language-prefixed path the dev/staging wikis serve under.
    this.scriptPath = process.env.MW_SCRIPT_PATH || '/he';
  }

  /** Form-based login via Special:UserLogin (goes through ResourceLoader + redirect). */
  async login(username: string, password: string): Promise<void> {
    await this.page.goto(
      `${this.scriptPath}/Special:UserLogin?returnto=Special:PromoterAds`
    );
    await this.page.getByRole('textbox').first().fill(username);
    // The password field is the second textbox; target by its input type to be safe.
    await this.page.locator('input[type="password"]').fill(password);
    await this.page.locator('button[type="submit"], #wpLoginAttempt').first().click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  async gotoAdManager(): Promise<void> {
    await this.page.goto(`${this.scriptPath}/Special:PromoterAds`);
    await this.page.waitForLoadState('domcontentloaded');
  }

  addAdButton(): Locator {
    return this.page.locator('#mw-input-wpaddNewAd');
  }

  removeSelectedButton(): Locator {
    return this.page.locator('#mw-input-wpdeleteSelectedAds');
  }

  adCheckboxes(): Locator {
    return this.page.locator('input.pr-adlist-check-applyto');
  }

  /** The currently-open OOUI dialog window (prompt or confirm). */
  openDialog(): Locator {
    return this.page.getByRole('dialog');
  }

  /**
   * Dismiss any open OOUI dialog by clicking its safe (cancel) action — the
   * reject action is flagged `safe` in both OO.ui.prompt and OO.ui.confirm, so
   * this cancels without confirming and without depending on button text.
   */
  async dismissDialog(): Promise<void> {
    await this.openDialog().locator('.oo-ui-flaggedElement-safe').first().click();
    await expect(this.openDialog()).toBeHidden();
  }
}
