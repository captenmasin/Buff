import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test, { mock } from 'node:test';
import axios from 'axios';
import { responseErrorMessage } from '../resources/js/foodRequests.ts';
import {
    overlayPhotoForPose,
    poseSortKey,
    progressPhotoCaptureLabels,
    progressPhotoPoses,
    selectPoseOverlays,
    sortProgressPhotos,
} from '../resources/js/progressPhotos.ts';

test('orders front, side, then back and leaves unlabeled photos last', () => {
    assert.deepEqual([...progressPhotoPoses], ['front', 'side', 'back']);
    assert.equal(progressPhotoCaptureLabels.front, 'Take front');
    assert.equal(poseSortKey('front'), 0);
    assert.equal(poseSortKey('side'), 1);
    assert.equal(poseSortKey('back'), 2);
    assert.equal(poseSortKey(null), 99);

    const sorted = sortProgressPhotos([
        { id: '2', pose: 'back' },
        { id: '0', pose: null },
        { id: '1', pose: 'front' },
        { id: '3', pose: 'side' },
    ]);

    assert.deepEqual(sorted.map((photo) => photo.id), ['1', '3', '2', '0']);
});

test('ghosts a matching pose and does not borrow a different labeled pose', () => {
    const photos = [
        { id: 'front', pose: 'front' },
        { id: 'back', pose: 'back' },
    ];

    assert.equal(overlayPhotoForPose(photos, 'front')?.id, 'front');
    assert.equal(overlayPhotoForPose(photos, 'side'), null);
    assert.equal(overlayPhotoForPose(photos, 'back')?.id, 'back');
});

test('falls back to photo order only when no poses are labeled', () => {
    const photos = [{ id: 'first', pose: null }, { id: 'second', pose: null }];

    assert.equal(overlayPhotoForPose(photos, 'front')?.id, 'first');
    assert.equal(overlayPhotoForPose(photos, 'side')?.id, 'second');
    assert.equal(overlayPhotoForPose(photos, 'back'), null);
});

test('ghosts each pose from the latest other day that has that pose', () => {
    const overlays = selectPoseOverlays([
        {
            date: '2026-08-20',
            photos: [
                { id: 'today-front', pose: 'front' },
                { id: 'today-back', pose: 'back' },
            ],
        },
        {
            date: '2026-08-10',
            photos: [
                { id: 'older-front', pose: 'front' },
                { id: 'older-side', pose: 'side' },
            ],
        },
    ], '2026-08-21');

    assert.equal(overlays.front?.photo.id, 'today-front');
    assert.equal(overlays.front?.date, '2026-08-20');
    assert.equal(overlays.side?.photo.id, 'older-side');
    assert.equal(overlays.side?.date, '2026-08-10');
    assert.equal(overlays.back?.photo.id, 'today-back');
    assert.equal(overlays.back?.date, '2026-08-20');
});

test('prefers another day over the current day when retaking photos', () => {
    const overlays = selectPoseOverlays([
        {
            date: '2026-08-20',
            photos: [{ id: 'same-front', pose: 'front' }],
        },
        {
            date: '2026-08-10',
            photos: [{ id: 'older-front', pose: 'front' }],
        },
    ], '2026-08-20');

    assert.equal(overlays.front?.photo.id, 'older-front');
    assert.equal(overlays.front?.date, '2026-08-10');
});

test('shows progress photos before notes and distinguishes the heading from pose labels', () => {
    const progressSource = readFileSync(new URL('../resources/js/Pages/Progress.vue', import.meta.url), 'utf8');

    assert.match(progressSource, /class="text-sm font-semibold text-foreground">Progress photos/);
    assert.match(progressSource, /class="field-label">\{\{ progressPhotoLabels\[pose\] \}\}/);
    assert.ok(progressSource.indexOf('>Progress photos<') < progressSource.indexOf('>Notes<'));
});

test('scrolls progress photo capture slots horizontally with a 2.5-card peek', () => {
    const progressSource = readFileSync(new URL('../resources/js/Pages/Progress.vue', import.meta.url), 'utf8');
    const captureSlots = progressSource.match(
        /Progress photos<\/p>\s*<div[\s\S]*?v-for="pose in progressPhotoPoses"[\s\S]*?<\/div>\s*<\/div>\s*<input/,
    )?.[0] ?? '';

    assert.match(captureSlots, /-mx-5/);
    assert.match(captureSlots, /auto-cols-\[calc\(\(100%-1\.5rem\)\/2\.5\)\]/);
    assert.match(captureSlots, /grid-flow-col/);
    assert.match(captureSlots, /overflow-x-auto/);
    assert.match(captureSlots, /gap-3/);
    assert.doesNotMatch(captureSlots, /grid-cols-3/);
    assert.match(captureSlots, /aspect-square/);
    assert.doesNotMatch(captureSlots, /aspect-\[3\/4\]/);
    assert.match(captureSlots, /progressPhotoCaptureLabels\[pose\]/);
    assert.match(captureSlots, /openLibrary\(pose\)/);
});

test('keeps native camera controls visible and lets Android Back close the camera', () => {
    const progressSource = readFileSync(new URL('../resources/js/Pages/Progress.vue', import.meta.url), 'utf8');
    const shellSource = readFileSync(new URL('../resources/js/Layouts/AppShell.vue', import.meta.url), 'utf8');

    assert.match(progressSource, /bg-foreground text-background/);
    assert.match(progressSource, /bg-background[^>]+text-foreground[^>]+safe-area-inset-top/);
    assert.match(progressSource, /bg-background\/10 text-background" aria-label="Flip camera"/);
    assert.match(progressSource, /window\.addEventListener\('buff:android-back', handleNativeAndroidBack\)/);
    assert.match(shellSource, /new Event\('buff:android-back', \{ cancelable: true \}\)/);
    assert.match(shellSource, /router\.visit\('\/settings', \{ replace: true \}\)/);
});

test('offers native app settings after camera permission is denied', () => {
    const progressSource = readFileSync(new URL('../resources/js/Pages/Progress.vue', import.meta.url), 'utf8');

    assert.match(progressSource, /cameraPermissionDenied\.value = cameraPermissionWasDenied\(error\)/);
    assert.match(progressSource, /await BridgeCall\('System\.OpenAppSettings'\)/);
    assert.match(progressSource, /v-if="cameraPermissionDenied && canOpenCameraSettings"[^>]+@click(?:\.stop)?="openCameraSettings"/);
    assert.match(progressSource, />\s*Open Buff settings\s*</);
});

test('hydrates dated check-ins and exposes every progress form error', () => {
    const progressSource = readFileSync(new URL('../resources/js/Pages/Progress.vue', import.meta.url), 'utf8');

    assert.match(progressSource, /\/progress\/body-metrics\/by-date\?\$\{params\.toString\(\)\}/);
    assert.match(progressSource, /hydrateMetricForm\(data\.metric \?\? null\)/);
    assert.match(progressSource, /type="date" :max="today"/);
    assert.match(progressSource, /metricForm\.errors\.notes/);
    assert.match(progressSource, /metricForm\.clearErrors\('notes'\)/);
});

test('keeps failed check-ins locked until a successful retry hydrates the selected date', () => {
    const progressSource = readFileSync(new URL('../resources/js/Pages/Progress.vue', import.meta.url), 'utf8');
    const loadMetric = progressSource.match(/async function loadMetricForDate\(date: string\): Promise<void> \{[\s\S]*?\n\}/)?.[0] ?? '';

    assert.match(progressSource, /function saveMetric\(\): void \{\s+if \(metricDateLoading\.value \|\| metricLoadError\.value \|\| metricForm\.errors\.date\) \{\s+return;/);
    assert.match(progressSource, /<fieldset :disabled="metricDateLoading \|\| Boolean\(metricLoadError\) \|\| Boolean\(metricForm\.errors\.date\)"/);
    assert.match(progressSource, /:disabled="metricDateLoading \|\| Boolean\(metricLoadError\) \|\| Boolean\(metricForm\.errors\.date\)"[\s\S]*?>\s*Save progress/);
    assert.match(progressSource, /@click="loadMetricForDate\(metricForm\.date\)">Retry loading check-in<\/Button>/);
    assert.match(loadMetric, /hydrateMetricForm\(data\.metric \?\? null\);\s+metricLoadError\.value = '';/);
    assert.doesNotMatch(loadMetric, /metricForm\.clearErrors\(\)|hydrateMetricForm\(null\)/);
});

function metricDateHarness(get: (url: string) => Promise<unknown>) {
    const source = readFileSync(new URL('../resources/js/Pages/Progress.vue', import.meta.url), 'utf8');
    const loader = source.match(/async function loadMetricForDate\(date: string\): Promise<void> \{[\s\S]*?\n\}/)?.[0]
        .replace('(date: string): Promise<void>', '(date)');
    const dateWatcher = source.match(/watch\(\(\) => metricForm.date, \(date\) => \{[\s\S]*?\n\}\);/)?.[0]
        .replace('void loadMetricForDate(date);', 'return loadMetricForDate(date);');
    assert.ok(loader);
    assert.ok(dateWatcher);

    const http = { get: mock.fn(get), isAxiosError: axios.isAxiosError };
    const hydrateMetricForm = mock.fn();
    const clearSelectedPhotos = mock.fn();
    const loadOverlayPhotos = mock.fn();
    const form = {
        errors: {} as Record<string, string>,
        setError(field: string, message: string) { this.errors[field] = message; },
        clearErrors(field?: string) {
            if (field) { delete this.errors[field]; } else { this.errors = {}; }
        },
    };
    const harness = new Function('axios', 'responseErrorMessage', 'metricForm', 'hydrateMetricForm', 'clearSelectedPhotos', 'loadOverlayPhotos', `
        const props = { today: '2026-09-04' };
        const metricDateLoading = { value: false };
        const metricLoadError = { value: '' };
        let metricDateRequest = 0;
        let selectDate;
        const watch = (_, callback) => { selectDate = callback; };
        ${loader}
        ${dateWatcher}
        return { props, metricDateLoading, metricLoadError, selectDate, retry: loadMetricForDate };
    `)(http, responseErrorMessage, form, hydrateMetricForm, clearSelectedPhotos, loadOverlayPhotos);

    return { ...harness, form, http, hydrateMetricForm, clearSelectedPhotos, loadOverlayPhotos };
}

test('explains future and empty check-in dates without fetching or offering a network retry', async () => {
    for (const [date, expected] of [['2026-09-05', 'Choose today or an earlier date.'], ['', 'Choose a date.']]) {
        const state = metricDateHarness(async () => { throw new Error('No request expected'); });

        await state.selectDate(date);

        assert.equal(state.form.errors.date, expected);
        assert.equal(state.metricLoadError.value, '');
        assert.equal(state.metricDateLoading.value, false);
        assert.equal(state.http.get.mock.callCount(), 0);
        assert.deepEqual(state.hydrateMetricForm.mock.calls.map(({ arguments: args }) => args), [[null]]);
        assert.equal(state.clearSelectedPhotos.mock.callCount(), 1);
    }
});

test('keeps a corrected date loading until its own check-in has hydrated', async () => {
    const response = Promise.withResolvers<{ data: { metric: { weight_kg: number } } }>();
    const state = metricDateHarness(() => response.promise);
    await state.selectDate('2026-09-05');

    const loading = state.selectDate('2026-09-04');

    assert.equal(state.form.errors.date, undefined);
    assert.equal(state.metricDateLoading.value, true);
    response.resolve({ data: { metric: { weight_kg: 75 } } });
    await loading;
    assert.equal(state.metricDateLoading.value, false);
    assert.equal(state.metricLoadError.value, '');
    assert.deepEqual(state.hydrateMetricForm.mock.calls.at(-1)?.arguments, [{ weight_kg: 75 }]);
});

test('shows the server date validation error when the client date limit is stale', async () => {
    const state = metricDateHarness(async () => {
        throw { isAxiosError: true, response: { status: 422, data: { errors: { date: ['The date must be a date before or equal to today.'] } } } };
    });
    state.props.today = '2026-09-05';

    await state.selectDate('2026-09-05');

    assert.equal(state.form.errors.date, 'The date must be a date before or equal to today.');
    assert.equal(state.metricLoadError.value, '');
    assert.equal(state.metricDateLoading.value, false);
});

test('keeps connection failures retryable and clears them only after successful hydration', async () => {
    let connected = false;
    const state = metricDateHarness(async () => {
        if (!connected) { throw new Error('Offline'); }
        return { data: { metric: null } };
    });
    await state.selectDate('2026-09-04');
    assert.match(state.metricLoadError.value, /Check your connection/);
    assert.equal(state.form.errors.date, undefined);
    connected = true;

    await state.retry('2026-09-04');

    assert.equal(state.metricLoadError.value, '');
    assert.equal(state.metricDateLoading.value, false);
    assert.equal(state.http.get.mock.callCount(), 2);
});

test('reads native JSON-string date validation without presenting a connection retry', async () => {
    const state = metricDateHarness(async () => {
        throw { isAxiosError: true, response: { status: 422, data: JSON.stringify({ errors: { date: ['Choose today or an earlier date.'] } }) } };
    });
    state.props.today = '2026-09-05';

    await state.selectDate('2026-09-05');

    assert.equal(state.form.errors.date, 'Choose today or an earlier date.');
    assert.equal(state.metricLoadError.value, '');
    assert.equal(state.metricDateLoading.value, false);
});

test('ignores late check-in success and validation errors after selecting another date', async () => {
    for (const outcome of ['success', 'validation']) {
        const response = Promise.withResolvers<unknown>();
        const state = metricDateHarness(() => response.promise);
        const previous = state.selectDate('2026-09-03');
        await state.selectDate('2026-09-05');

        if (outcome === 'success') {
            response.resolve({ data: { metric: { weight_kg: 76 } } });
        } else {
            response.reject({ isAxiosError: true, response: { status: 422, data: { errors: { date: ['Stale server date error'] } } } });
        }
        await previous;

        assert.equal(state.form.errors.date, 'Choose today or an earlier date.');
        assert.equal(state.metricLoadError.value, '');
        assert.equal(state.metricDateLoading.value, false);
        assert.deepEqual(state.hydrateMetricForm.mock.calls.map(({ arguments: args }) => args), [[null], [null]]);
        assert.equal(state.loadOverlayPhotos.mock.callCount(), 0);
    }
});

test('keeps failed photo loads retryable and every pose retakeable', () => {
    const progressSource = readFileSync(new URL('../resources/js/Pages/Progress.vue', import.meta.url), 'utf8');

    assert.doesNotMatch(progressSource, /\.catch\(\(\) => cachePhotos\(metricId, \[\]\)\)/);
    assert.match(progressSource, /Could not load progress photos\. Check your connection and try again\./);
    assert.match(progressSource, /Could not prepare that image\. Choose another photo\./);
    assert.match(progressSource, /Retake \$\{photoPoseLabel\(photo\.pose\)\.toLowerCase\(\)\} photo/);
    assert.match(progressSource, /data-native-overlay/);
});
