import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { stripTypeScriptTypes } from 'node:module';
import axios from 'axios';
import { responseErrorMessage } from '../resources/js/foodRequests.ts';

function mealPhotoLoader(get: (url: string) => Promise<unknown>) {
    const source = readFileSync(new URL('../resources/js/Pages/Today.vue', import.meta.url), 'utf8');
    const loadFunction = source.slice(source.indexOf('async function loadMealPhotos('), source.indexOf('function closeMeal('));
    const selectedMealPhotos = { value: [] };
    const mealPhotosLoading = { value: false };
    const mealPhotosError = { value: '' };
    const load = new Function('axios', 'responseErrorMessage', 'selectedMealPhotos', 'mealPhotosLoading', 'mealPhotosError', `
        let mealPhotoRequest = 0;
        ${stripTypeScriptTypes(loadFunction)}
        return loadMealPhotos;
    `)({ get, isAxiosError: axios.isAxiosError }, responseErrorMessage, selectedMealPhotos, mealPhotosLoading, mealPhotosError);

    return { load, photos: selectedMealPhotos, loading: mealPhotosLoading, error: mealPhotosError };
}

test('keeps a missing meal gallery quiet but exposes other failures and clears them on retry', async () => {
    let response: unknown = { isAxiosError: true, response: { status: 404 } };
    const loader = mealPhotoLoader(async (url) => {
        assert.equal(url, '/meals/meal-a/photos');

        if ((response as { isAxiosError?: boolean }).isAxiosError) {
            throw response;
        }

        return response;
    });

    await loader.load('meal-a');
    assert.deepEqual(loader.photos.value, []);
    assert.equal(loader.error.value, '');
    assert.equal(loader.loading.value, false);

    for (const status of [401, 500]) {
        response = { isAxiosError: true, response: { status, data: JSON.stringify({ message: 'Photo request failed.' }) } };
        await loader.load('meal-a');
        assert.equal(loader.error.value, 'Photo request failed.');
        assert.equal(loader.loading.value, false);
    }

    response = { data: { photos: [{ id: 'photo-a', url: 'https://example.test/photo.jpg' }] } };
    const retry = loader.load('meal-a');
    assert.equal(loader.error.value, '');
    assert.equal(loader.loading.value, true);
    await retry;
    assert.deepEqual(loader.photos.value, [{ id: 'photo-a', url: 'https://example.test/photo.jpg' }]);
    assert.equal(loader.loading.value, false);
});

test('shows connection failure without allowing an older failed request to replace the current gallery', async () => {
    let rejectOlder: (error: unknown) => void = () => {};
    const loader = mealPhotoLoader((url) => url === '/meals/older/photos'
        ? new Promise((resolve, reject) => { rejectOlder = reject; })
        : Promise.resolve({ data: { photos: [{ id: 'current-photo' }] } }));
    const older = loader.load('older');
    await loader.load('current');

    rejectOlder(new Error('Connection lost'));
    await older;

    assert.deepEqual(loader.photos.value, [{ id: 'current-photo' }]);
    assert.equal(loader.error.value, '');
    assert.equal(loader.loading.value, false);

    const offline = mealPhotoLoader(async () => { throw new Error('Connection lost'); });
    await offline.load('meal-a');
    assert.equal(offline.error.value, 'Could not load meal photos. Check your connection and try again.');
});

test('keeps the current loading state when an older gallery response arrives', async () => {
    let resolveOlder: (response: unknown) => void = () => {};
    let resolveCurrent: (response: unknown) => void = () => {};
    const loader = mealPhotoLoader((url) => new Promise((resolve) => {
        if (url === '/meals/older/photos') {
            resolveOlder = resolve;
        } else {
            resolveCurrent = resolve;
        }
    }));
    const older = loader.load('older');
    const current = loader.load('current');

    resolveOlder({ data: { photos: [{ id: 'older-photo' }] } });
    await older;

    assert.deepEqual(loader.photos.value, []);
    assert.equal(loader.error.value, '');
    assert.equal(loader.loading.value, true);

    resolveCurrent({ data: { photos: [{ id: 'current-photo' }] } });
    await current;

    assert.deepEqual(loader.photos.value, [{ id: 'current-photo' }]);
    assert.equal(loader.loading.value, false);
});

test('renders retryable meal-photo feedback and clears it when the drawer closes', () => {
    const source = readFileSync(new URL('../resources/js/Pages/Today.vue', import.meta.url), 'utf8');
    const closeFunction = source.slice(source.indexOf('function closeMeal('), source.indexOf('function repeatMeal('));

    assert.match(source, /v-else-if="mealPhotosError"[^>]*role="alert"/);
    assert.match(source, /@click="loadMealPhotos\(selectedMeal\.id\)"[^>]*>Retry meal photos<\/Button>/);
    assert.match(closeFunction, /mealPhotosError\.value = ''/);
});

test('uses separate camera capture and photo library inputs', () => {
    const source = readFileSync(new URL('../resources/js/Pages/Add.vue', import.meta.url), 'utf8');

    assert.match(source, /ref="photoCameraInput"[^>]+capture="environment"/);
    assert.match(source, /ref="photoLibraryInput"[^>]+multiple/);
    assert.match(source, /@click="photoCameraInput\?\.click\(\)"[^>]*>[\s\S]*?Take photo/);
    assert.match(source, /@click="photoLibraryInput\?\.click\(\)"[^>]*>[\s\S]*?Choose photo/);
});

test('shows a loading state while preparing photo previews', () => {
    const source = readFileSync(new URL('../resources/js/Pages/Add.vue', import.meta.url), 'utf8');

    assert.match(source, /photoProcessing\.value = true;[\s\S]*?finally \{[\s\S]*?photoProcessing\.value = false;/);
    assert.match(source, /v-if="photoProcessing"[^>]+role="status"[^>]+aria-live="polite"/);
    assert.match(source, /Preparing photo…/);
});

test('keeps meal photo controls and shows an accessible reduced-motion analysis state', () => {
    const source = readFileSync(new URL('../resources/js/Pages/Add.vue', import.meta.url), 'utf8');

    assert.match(source, /Context \(optional\)/);
    assert.match(source, /Buff Vision · analyzing/);
    assert.match(source, /role="status"[^>]+aria-live="polite"[^>]+aria-label="Buff Vision is analyzing your meal"/);
    assert.match(source, /@media \(prefers-reduced-motion: reduce\)/);
});
