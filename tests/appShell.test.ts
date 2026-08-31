import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

test('uses dark text for active bottom navigation links in dark mode', () => {
    const source = readFileSync(new URL('../resources/js/Layouts/AppShell.vue', import.meta.url), 'utf8');

    assert.equal(source.match(/bg-primary text-brand-night/g)?.length, 4);
    assert.equal(source.match(/isActive\(navItems\[\d\]\.match\) \? 'default' : 'ghost'/g)?.length, 4);
});

test('keeps the settings back control separate from the page title', () => {
    const source = readFileSync(new URL('../resources/js/Components/SettingsPageHeader.vue', import.meta.url), 'utf8');

    assert.match(source, /items-center gap-1/);
    assert.doesNotMatch(source, /absolute left-0/);
});

test('shows the Buff logo in setup flow headers', () => {
    const source = readFileSync(new URL('../resources/js/Components/SetupFlow.vue', import.meta.url), 'utf8');

    assert.match(source, /publicAssetUrl\('\/logo\.svg'\)/);
    assert.match(source, /publicAssetUrl\('\/logo-dark\.svg'\)/);
});

test('shows the health provider name only in the settings page header', () => {
    const source = readFileSync(new URL('../resources/js/Pages/Settings/Health.vue', import.meta.url), 'utf8');

    assert.match(source, /<SettingsPageHeader>{{ healthImport\?\.name \?\? 'Health' }}<\/SettingsPageHeader>/);
    assert.doesNotMatch(source, /<h2[^>]*>{{ healthImport\?\.name }}<\/h2>/);
});

test('configures subscriptions from the signed-in shell and gates the Photo shortcut', () => {
    const source = readFileSync(new URL('../resources/js/Layouts/AppShell.vue', import.meta.url), 'utf8');

    assert.match(source, /configureCurrentAccount\(page\.props\.buff\.account\)/);
    assert.match(source, /mode === 'photo' && !subscriptionActive\.value/);
    assert.match(source, /router\.visit\('\/settings\/subscription'\)/);
});

test('hands native ad reconciliation to the shell lifecycle and measured mobile navigation', () => {
    const source = readFileSync(new URL('../resources/js/Layouts/AppShell.vue', import.meta.url), 'utf8');
    const styles = readFileSync(new URL('../resources/css/app.css', import.meta.url), 'utf8');

    assert.match(source, /createAdCoordinator/);
    assert.match(source, /router\.on\('before'/);
    assert.match(source, /adCoordinator\.beforeNavigation/);
    assert.match(source, /adCoordinator\.beforeNavigation\('\/add'\)/);
    assert.match(source, /addDrawerOpen\.value \? '\/add' : url/);
    assert.match(source, /ref="mobileNavContent"/);
    assert.match(source, /getBoundingClientRect\(\)\.height/);
    assert.match(source, /needs_sign_in === false/);
    assert.match(source, /adCoordinator\.destroy\(\)/);
    assert.match(styles, /var\(--ad-banner-height, 0px\)/);
});

test('refreshes UMP privacy status with the shared teen-safe audience fallback', () => {
    const source = readFileSync(new URL('../resources/js/Pages/Settings.vue', import.meta.url), 'utf8');
    const ads = readFileSync(new URL('../resources/js/ads.ts', import.meta.url), 'utf8');

    assert.match(source, /adPrivacyOptionsRequired\(page\.props\.buff\.ad_audience \?\? 'teen'\)/);
    assert.match(ads, /adPrivacyOptionsRequired[\s\S]+bridge\.ump\.requestInfo\(\)[\s\S]+privacyOptionsRequired === true/);
});
