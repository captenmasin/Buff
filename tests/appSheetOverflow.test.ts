import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { stripTypeScriptTypes } from 'node:module';
import test from 'node:test';

const appSheetSource = readFileSync(new URL('../resources/js/Components/AppSheet.vue', import.meta.url), 'utf8');
const sheetContentSource = readFileSync(new URL('../resources/js/Components/ui/sheet/SheetContent.vue', import.meta.url), 'utf8');
const appStyles = readFileSync(new URL('../resources/css/app.css', import.meta.url), 'utf8');
const confirmSheetSource = readFileSync(new URL('../resources/js/Components/ConfirmSheet.vue', import.meta.url), 'utf8');
const appShellSource = readFileSync(new URL('../resources/js/Layouts/AppShell.vue', import.meta.url), 'utf8');

function sourceFunction(source: string, name: string): string {
    const declaration = source.match(new RegExp(`function ${name}\\([^]*?\\n}`))?.[0];
    assert.ok(declaration, `${name} must exist`);

    return stripTypeScriptTypes(declaration);
}

function androidBackHarness() {
    const window = Object.assign(new EventTarget(), { history: { length: 2, back: () => { navigations++; } } });
    let navigations = 0;
    const elements: { dataset: { appSheet: string; state: string } }[] = [];
    const document = {
        querySelectorAll(selector: string) {
            assert.equal(selector, '[data-app-sheet][data-state="open"]');
            const openElements = elements.filter((element) => element.dataset.state === 'open');

            return { length: openElements.length, item: (index: number) => openElements[index] };
        },
    };
    const back = new Function('window', 'Event', 'addDrawerOpen', 'isSettingsSubpage', `${sourceFunction(appShellSource, 'handleNativeAndroidBack')} return handleNativeAndroidBack;`)(window, Event, { value: false }, { value: false });

    function add(kind: 'sheet' | 'confirm', id: string, locked = false) {
        const source = kind === 'confirm' ? confirmSheetSource : appSheetSource;
        const props = { open: true, labelledBy: id, dismissible: !locked, processing: locked };
        const events: string[] = [];
        const element = { dataset: { appSheet: id, state: 'open' } };
        elements.push(element);
        const emit = (event: string) => {
            events.push(event);
            props.open = false;
            element.dataset.state = 'closed';
        };
        const handler = new Function('props', 'emit', 'document', 'sheetId', `${sourceFunction(source, 'onOpenChange')} ${sourceFunction(source, 'handleNativeAndroidBack')} return handleNativeAndroidBack;`)(props, emit, document, id);
        window.addEventListener('buff:android-back', handler);

        return { events, props, element };
    }

    return { add, back, window, elements, navigations: () => navigations };
}

test('keeps drawers and modals scrollable on short viewports', () => {
    assert.match(appSheetSource, /max-h-\[88dvh\][^']*overflow-y-auto[^']*overscroll-contain/);
    assert.match(appSheetSource, /max-h-\[calc\(100dvh-2rem\)\][^']*overflow-y-auto[^']*overscroll-contain/);
});

test('centers drawers as dialogs on desktop', () => {
    assert.match(appStyles, /@media \(width >= 40rem\)[\s\S]*?\.bottom-drawer \{[\s\S]*?inset: 1rem 0 1rem 16rem;[\s\S]*?height: fit-content;[\s\S]*?margin: auto;[\s\S]*?border-radius: var\(--radius-xl\);/);
});

test('hides scrollbars without disabling scrolling', () => {
    assert.match(appStyles, /\* \{[\s\S]*?scrollbar-width: none;/);
    assert.match(appStyles, /\*::-webkit-scrollbar \{[\s\S]*?display: none;/);
    assert.doesNotMatch(appStyles, /@media \(hover: none\), \(pointer: coarse\) \{[\s\S]*?scrollbar-width: none;/);
});

test('hides the drawer grabber on desktop', () => {
    assert.match(appSheetSource, /active:cursor-grabbing sm:hidden/);
    assert.match(appSheetSource, /isDesktopDrawer\(\)/);
});

test('transitions the translate property used to slide sheets', () => {
    assert.match(sheetContentSource, /transition-\[translate,transform,opacity\]/);
});

test('registers an accessible title and description for every sheet variant', () => {
    assert.equal(appSheetSource.match(/<DialogTitle class="sr-only">\{\{ title \}\}<\/DialogTitle>/g)?.length, 2);
    assert.equal(appSheetSource.match(/<DialogDescription class="sr-only">\{\{ description \}\}<\/DialogDescription>/g)?.length, 2);
});

test('lets only the topmost sheet consume Android Back', () => {
    for (const source of [appSheetSource, confirmSheetSource]) {
        assert.match(source, /querySelectorAll<HTMLElement>\('\[data-app-sheet\]\[data-state="open"\]'\)/);
        assert.match(source, /window\.addEventListener\('buff:android-back', handleNativeAndroidBack\)/);
        assert.match(source, /window\.removeEventListener\('buff:android-back', handleNativeAndroidBack\)/);
    }
    assert.equal(appSheetSource.match(/:data-app-sheet="labelledBy"/g)?.length, 2);
    assert.match(confirmSheetSource, /const sheetId = useId\(\)/);
    assert.match(confirmSheetSource, /<AlertDialogContent[^>]*:data-app-sheet="sheetId"/);
});

test('cancels an idle confirmation on Android Back without confirming or navigating', () => {
    const harness = androidBackHarness();
    const confirmation = harness.add('confirm', 'delete-recipe');

    assert.equal(harness.back(), true);
    assert.deepEqual(confirmation.events, ['cancel']);
    assert.equal(harness.navigations(), 0);

    assert.equal(harness.back(), true);
    assert.deepEqual(confirmation.events, ['cancel']);
    assert.equal(harness.navigations(), 1);
});

test('consumes Android Back without dismissing a processing confirmation or locked sheet', () => {
    for (const kind of ['confirm', 'sheet'] as const) {
        const harness = androidBackHarness();
        const underlyingSheet = harness.add('sheet', 'underlying');
        const dialog = harness.add(kind, 'processing', true);

        assert.equal(harness.back(), true);
        assert.deepEqual(dialog.events, []);
        assert.deepEqual(underlyingSheet.events, []);
        assert.equal(dialog.props.open, true);
        assert.equal(harness.navigations(), 0);
    }
});

test('dismisses only the topmost open dialog across mixed sheets and confirmations', () => {
    for (const kinds of [['sheet', 'confirm'], ['confirm', 'sheet'], ['confirm', 'confirm']] as const) {
        for (const topRegisteredFirst of [false, true]) {
            const harness = androidBackHarness();
            const lower = harness.add(kinds[0], 'lower');
            const upper = harness.add(kinds[1], 'upper');
            const top = topRegisteredFirst ? lower : upper;
            const underneath = topRegisteredFirst ? upper : lower;

            if (topRegisteredFirst) {
                harness.elements.reverse();
            }

            harness.back();

            assert.deepEqual(top.events, [kinds[topRegisteredFirst ? 0 : 1] === 'confirm' ? 'cancel' : 'close']);
            assert.deepEqual(underneath.events, []);
            assert.equal(harness.navigations(), 0);
        }
    }
});

test('ignores closing animation content when finding the topmost open dialog', () => {
    const harness = androidBackHarness();
    const sheet = harness.add('sheet', 'gallery');
    const closingConfirmation = harness.add('confirm', 'closed-confirmation');
    closingConfirmation.element.dataset.state = 'closed';

    harness.back();

    assert.deepEqual(sheet.events, ['close']);
    assert.deepEqual(closingConfirmation.events, []);
    assert.equal(harness.navigations(), 0);
});
