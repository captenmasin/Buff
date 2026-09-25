import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { router, useForm } from '@inertiajs/vue3';
import { compileTemplate, parse } from 'vue/compiler-sfc';

function historyNavigationHandler() {
    const source = readFileSync(new URL('../resources/js/Layouts/AppShell.vue', import.meta.url), 'utf8');
    const handler = source.match(/removeHistoryNavigationListener = router\.on\('navigate', ([\s\S]*?)\n    }\);/)?.[1];
    assert.ok(handler, 'History restoration must refresh current shared metadata');

    return new Function('router', `return ${handler}\n}`)(router);
}

test('refreshes only current server metadata after Back without resetting a dirty form', (context) => {
    const handler = historyNavigationHandler();
    const form = useForm({ name: 'Saved recipe' });
    form.name = 'Unsaved recipe change';
    form.setError('name', 'Existing field feedback');
    const requests: { url: string; options: Record<string, unknown> }[] = [];
    const originalWindow = Object.getOwnPropertyDescriptor(globalThis, 'window');
    Object.defineProperty(globalThis, 'window', { configurable: true, value: { location: { href: 'http://127.0.0.1/add?mode=recipe' } } });
    context.after(() => {
        if (originalWindow) Object.defineProperty(globalThis, 'window', originalWindow);
        else Reflect.deleteProperty(globalThis, 'window');
    });
    context.mock.method(router, 'visit', (url, options) => {
        requests.push({ url: String(url), options });
    });

    handler({ detail: { page: { url: '/add?mode=recipe', props: { buff: { account: { id: 'old-account' }, sync: { pending: 1, last_error: 'Old error' } } } } } });

    assert.equal(requests.length, 1);
    assert.equal(requests[0].url, 'http://127.0.0.1/add?mode=recipe');
    assert.deepEqual(requests[0].options.only, ['buff', 'flash']);
    assert.equal(requests[0].options.preserveErrors, true);
    assert.equal(requests[0].options.preserveState, true);
    assert.equal(requests[0].options.preserveScroll, true);
    assert.equal(requests[0].options.async, true);
    assert.equal(requests[0].options.method, undefined);
    assert.equal(requests[0].options.data, undefined);
    assert.equal(form.name, 'Unsaved recipe change');
    assert.equal(form.errors.name, 'Existing field feedback');
});

test('does not refresh metadata for a normal visit or its own reload response', (context) => {
    const handler = historyNavigationHandler();
    const reload = context.mock.method(router, 'reload', () => {});

    handler({ detail: { page: {}, visitId: 'server-visit', cached: false } });
    handler({ detail: { page: {}, visitId: 'client-visit', cached: true } });
    handler({ detail: { page: {}, visitId: 'metadata-reload', cached: false } });

    assert.equal(reload.mock.callCount(), 0);
});

test('removes the history metadata listener when the account layout unmounts', () => {
    const source = readFileSync(new URL('../resources/js/Layouts/AppShell.vue', import.meta.url), 'utf8');
    const cleanup = source.slice(source.indexOf('onUnmounted(() => {'));

    assert.match(cleanup, /if \(removeHistoryNavigationListener\) \{\s+removeHistoryNavigationListener\(\);\s+removeHistoryNavigationListener = null;/);
});

test('compiles the app shell template', () => {
    const source = readFileSync(new URL('../resources/js/Layouts/AppShell.vue', import.meta.url), 'utf8');
    const { descriptor } = parse(source);

    assert.deepEqual(compileTemplate({ source: descriptor.template!.content, filename: 'AppShell.vue', id: 'app-shell' }).errors, []);
});

test('keeps a themed non-interactive top safe-area backing outside every page scroll', () => {
    const styles = readFileSync(new URL('../resources/css/app.css', import.meta.url), 'utf8');
    const backing = styles.match(/#app::before\s*\{([^}]+)\}/)?.[1];

    assert.ok(backing, 'The shared Inertia root must protect both shell and standalone account pages');
    assert.match(backing, /content:\s*'';/);
    assert.match(backing, /position:\s*fixed;/);
    assert.match(backing, /inset:\s*0 0 auto;/);
    assert.match(backing, /height:\s*env\(safe-area-inset-top, 0px\);/);
    assert.match(backing, /background:\s*var\(--background\);/);
    assert.match(backing, /pointer-events:\s*none;/);
    assert.doesNotMatch(backing, /(?:padding|margin|transform|opacity):/);
});

test('keeps top safe-area backing below offline feedback and modal layers', () => {
    const styles = readFileSync(new URL('../resources/css/app.css', import.meta.url), 'utf8');
    const backing = styles.match(/#app::before\s*\{([^}]+)\}/)?.[1];
    assert.ok(backing);
    const backingLayer = Number(backing.match(/z-index:\s*(\d+);/)?.[1]);

    assert.equal(backingLayer, 30);
    for (const path of [
        '../resources/js/Components/OfflineBanner.vue',
        '../resources/js/Components/ui/sheet/SheetOverlay.vue',
        '../resources/js/Components/ui/dialog/DialogOverlay.vue',
        '../resources/js/Components/ui/alert-dialog/AlertDialogContent.vue',
    ]) {
        const source = readFileSync(new URL(path, import.meta.url), 'utf8');
        const overlayLayer = Number(source.match(/\bz-(\d+)\b/)?.[1]);
        assert.ok(overlayLayer > backingLayer, `${path} must remain above the decorative backing`);
    }
});

test('uses dark text for active bottom navigation links in dark mode', () => {
    const source = readFileSync(new URL('../resources/js/Layouts/AppShell.vue', import.meta.url), 'utf8');

    assert.equal(source.match(/bg-primary text-brand-night/g)?.length, 4);
    assert.equal(source.match(/isActive\(navItems\[\d\]\.match\) \? 'default' : 'ghost'/g)?.length, 4);
});

test('keeps the settings back control separate from the page title', () => {
    const source = readFileSync(new URL('../resources/js/Components/SettingsPageHeader.vue', import.meta.url), 'utf8');

    assert.match(source, /items-center gap-1/);
    assert.match(source, /:href="backHref"\s+replace/);
    assert.doesNotMatch(source, /absolute left-0/);
});

test('names settings selection and inventory controls for assistive technology', () => {
    const exercise = readFileSync(new URL('../resources/js/Pages/Settings/Exercise.vue', import.meta.url), 'utf8');
    const assistants = readFileSync(new URL('../resources/js/Pages/Settings/ConnectedAssistants.vue', import.meta.url), 'utf8');

    assert.match(exercise, /role="radiogroup" aria-label="Exercise calorie eat-back"/);
    assert.match(exercise, /role="radio"/);
    assert.match(exercise, /:aria-checked="eatBackForm\.eat_back === option\.value"/);
    assert.match(assistants, /aria-label="Authorized assistants"/);
    assert.doesNotMatch(assistants, /aria-labelledby="authorized-assistants-heading"/);
});

test('keeps connected assistants MCP setup phone-first without desktop Codex instructions', () => {
    const assistants = readFileSync(new URL('../resources/js/Pages/Settings/ConnectedAssistants.vue', import.meta.url), 'utf8');

    assert.match(assistants, /Connect from your phone/);
    assert.match(assistants, /shareMcpEndpoint/);
    assert.match(assistants, /openAssistantSetup/);
    assert.doesNotMatch(assistants, /Desktop setup/);
    assert.doesNotMatch(assistants, /codex mcp/);
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
    assert.match(source, /addDrawerOpen\.value \|\| adOverlayOpen \? '\/overlay' : url/);
    assert.match(source, /ref="mobileNavContent"/);
    assert.match(source, /getBoundingClientRect\(\)\.height/);
    assert.match(source, /needs_sign_in === false/);
    assert.match(source, /adCoordinator\.destroy\(\)/);
    assert.match(styles, /var\(--ad-banner-height, 0px\)/);
});

test('exposes active navigation and retryable sync state', () => {
    const source = readFileSync(new URL('../resources/js/Layouts/AppShell.vue', import.meta.url), 'utf8');

    assert.equal(source.match(/:aria-current="isActive\([^)]*\) \? 'page' : undefined"/g)?.length, 5);
    assert.match(source, /buffSyncStatus\(page\.props\.buff\.sync, syncInProgress\.value\)/);
    assert.match(source, /Retry sync/);
    assert.match(source, /:role="syncStatus\.kind === 'failed' \? 'alert' : 'status'"/);
});

test('hides the native banner while sheets and modals cover the app', () => {
    const source = readFileSync(new URL('../resources/js/Layouts/AppShell.vue', import.meta.url), 'utf8');

    assert.match(source, /MutationObserver\(syncAdOverlayVisibility\)/);
    assert.match(source, /sheet-overlay/);
    assert.match(source, /dialog-overlay/);
    assert.match(source, /alert-dialog-overlay/);
    assert.match(source, /data-native-overlay/);
    assert.match(source, /adCoordinator\.beforeNavigation\('\/overlay'\)/);
    assert.match(source, /adOverlayObserver\?\.disconnect\(\)/);
});

test('refreshes UMP privacy status with the shared teen-safe audience fallback', () => {
    const source = readFileSync(new URL('../resources/js/Pages/Settings.vue', import.meta.url), 'utf8');
    const ads = readFileSync(new URL('../resources/js/ads.ts', import.meta.url), 'utf8');

    assert.match(source, /adPrivacyOptionsRequired\(page\.props\.buff\.ad_audience \?\? 'teen'\)/);
    assert.match(ads, /adPrivacyOptionsRequired[\s\S]+bridge\.ump\.requestInfo\(\)[\s\S]+privacyOptionsRequired === true/);
});
