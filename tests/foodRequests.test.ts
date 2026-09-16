import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {stripTypeScriptTypes} from 'node:module';
import test from 'node:test';
import {compile, ref} from 'vue';
import {foodSearchUrl, responseErrorMessage} from '../resources/js/foodRequests.ts';

test('updates shared input values before notifying search listeners', () => {
    const source = readFileSync(new URL('../resources/js/Components/ui/input/Input.vue', import.meta.url), 'utf8');
    const template = source.match(/<template>([\s\S]*?)<\/template>/)?.[1];
    const declaration = source.match(/function updateValue\(event: Event\): void \{[\s\S]*?\n\}/)?.[0];
    assert.ok(template);
    assert.ok(declaration);
    class InputTarget { value = ''; }

    for (const [number, values, expected] of [
        [false, ['A', 'Ab', 'Thai peanut', ''], ['A', 'Ab', 'Thai peanut', '']],
        [true, ['0', '0.2', ''], [0, 0.2, '']],
    ] as const) {
        const model = ref<string | number>('');
        const observed: (string | number)[] = [];
        const updateValue = new Function('HTMLInputElement', 'model', 'modifiers', `${stripTypeScriptTypes(declaration)}; return updateValue;`)(InputTarget, model, {number});
        const vnode = compile(template)({
            attrs: {onInput: () => observed.push(model.value)}, updateValue,
            inputValue: '', cn: () => '', props: {class: ''},
        }, []);
        const target = new InputTarget();

        for (const value of values) {
            target.value = value;
            for (const handler of [vnode.props!.onInput].flat()) handler({target});
        }

        assert.deepEqual(observed, expected);
    }
});

test('serializes the visible food query and device locale into the request URL', () => {
    assert.equal(foodSearchUrl('crème & yogurt', 'fr-FR'), '/food-products/search?q=cr%C3%A8me+%26+yogurt&locale=fr-FR');
});

for (const phase of ['queued', 'pending success', 'pending failure']) {
    test(`keeps recipe search cleared after selecting a product with a ${phase} request`, async (context) => {
        context.mock.timers.enable({apis: ['setTimeout']});
        const source = readFileSync(new URL('../resources/js/Components/RecipeMode.vue', import.meta.url), 'utf8');
        const declarations = ['round', 'macrosFor', 'resetFoodSearch', 'queueFoodSearch', 'searchFoods', 'addProduct'].map((name) => {
            const declaration = source.match(new RegExp(`(?:async )?function ${name}\\([^]*?\\n\\}`))?.[0];
            assert.ok(declaration, name);
            return declaration;
        }).join('\n');
        const response = Promise.withResolvers<{data: {products: {id: string}[]}}>();
        const get = context.mock.fn(() => response.promise);
        const controls = new Function('ref', 'window', 'navigator', 'axios', 'foodSearchUrl', 'responseErrorMessage', `
            const searchQuery = ref('Thai sesame noodles'), searchResults = ref([]), searchError = ref(''), searchLoading = ref(false);
            const recipeForm = {items: []};
            let searchRequest = 0, searchTimer = 0;
            ${stripTypeScriptTypes(declarations)}
            return {queueFoodSearch, addProduct, searchQuery, searchResults, searchError, searchLoading, recipeForm};
        `)(ref, globalThis, {language: 'en-US'}, {get}, foodSearchUrl, responseErrorMessage);

        controls.queueFoodSearch();
        if (phase !== 'queued') context.mock.timers.tick(250);
        controls.addProduct({id: 'selected', name: 'Oat milk', nutrition_unit: 'ml', calories_per_100: 61, protein_per_100: 1, carbs_per_100: 7, fat_per_100: 3});
        context.mock.timers.tick(250);
        if (phase === 'pending failure') response.reject({response: {data: {message: 'Late search failure'}}});
        else response.resolve({data: {products: [{id: 'late'}]}});
        await response.promise.catch(() => {});

        assert.equal(get.mock.callCount(), phase === 'queued' ? 0 : 1);
        assert.equal(controls.searchQuery.value, '');
        assert.deepEqual(controls.searchResults.value, []);
        assert.equal(controls.searchError.value, '');
        assert.equal(controls.searchLoading.value, false);
        assert.deepEqual(controls.recipeForm.items, [{name: 'Oat milk', food_product_id: 'selected', portion_quantity: 100, portion_unit: 'ml', calories: 61, protein_g: 1, carbs_g: 7, fat_g: 3}]);
    });
}

for (const [screen, path, searchFunction] of [
    ['All foods', '../resources/js/Pages/Add.vue', 'searchFoodProducts'],
    ['Recipes', '../resources/js/Components/RecipeMode.vue', 'searchFoods'],
]) {
    test(`debounces ${screen} queries and ignores replies invalidated before the next request`, async (context) => {
        context.mock.timers.enable({apis: ['setTimeout']});
        const source = readFileSync(new URL(path, import.meta.url), 'utf8');
        const declarations = ['queueFoodSearch', 'retryFoodSearch', searchFunction].map((name) => {
            const declaration = source.match(new RegExp(`(?:async )?function ${name}\\([^]*?\\n\\}`))?.[0];
            assert.ok(declaration, name);
            return declaration;
        }).join('\n');
        const requests: (ReturnType<typeof Promise.withResolvers<{data: {products: {id: string; type?: string}[]}}>> & {url: string})[] = [];
        const get = (url: string) => {
            const response = Promise.withResolvers<{data: {products: {id: string; type?: string}[]}}>();
            requests.push({...response, url});
            return response.promise;
        };
        const controls = new Function('ref', 'window', 'navigator', 'axios', 'foodSearchUrl', 'responseErrorMessage', `
            const foodSearch = ref(''), searchQuery = foodSearch;
            const foodSearchResults = ref([]), searchResults = foodSearchResults;
            const foodSearchError = ref(''), searchError = foodSearchError;
            const foodSearchLoading = ref(false), searchLoading = foodSearchLoading;
            let foodSearchRequestId = 0, searchRequest = 0, foodSearchTimer = 0, searchTimer = 0;
            ${stripTypeScriptTypes(declarations)}
            return {queueFoodSearch, retryFoodSearch, query: foodSearch, results: foodSearchResults, error: foodSearchError, loading: foodSearchLoading};
        `)(ref, globalThis, {language: 'en-US'}, {get}, foodSearchUrl, responseErrorMessage);

        controls.query.value = 'mi';
        controls.queueFoodSearch();
        context.mock.timers.tick(100);
        controls.query.value = ' milk ';
        controls.queueFoodSearch();
        context.mock.timers.tick(249);
        assert.equal(requests.length, 0);
        context.mock.timers.tick(1);
        assert.equal(requests[0].url, '/food-products/search?q=milk&locale=en-US');

        controls.query.value = 'oat';
        controls.queueFoodSearch();
        requests[0].resolve({data: {products: [{id: 'old-milk'}]}});
        await requests[0].promise;
        assert.deepEqual(controls.results.value, []);
        assert.equal(controls.loading.value, true);
        context.mock.timers.tick(250);
        assert.equal(requests[1].url, '/food-products/search?q=oat&locale=en-US');
        const products = [{id: 'oat'}, {id: 'history', type: 'previous_meal'}];
        requests[1].resolve({data: {products}});
        await requests[1].promise;
        assert.deepEqual(controls.results.value, screen === 'Recipes' ? [{id: 'oat'}] : products);

        controls.query.value = 'chocolate';
        controls.queueFoodSearch();
        context.mock.timers.tick(250);
        controls.query.value = '';
        controls.queueFoodSearch();
        requests[2].reject({response: {data: {message: 'Stale request failed'}}});
        await requests[2].promise.catch(() => {});
        assert.deepEqual(controls.results.value, []);
        assert.equal(controls.error.value, '');
        assert.equal(controls.loading.value, false);
        controls.query.value = 'A';
        controls.queueFoodSearch();
        context.mock.timers.tick(250);
        assert.equal(requests.length, 3);

        controls.query.value = 'milk';
        controls.queueFoodSearch();
        controls.retryFoodSearch();
        context.mock.timers.tick(250);
        assert.equal(requests.length, 4);
        requests[3].resolve({data: {products: [{id: 'fresh-milk'}]}});
        await requests[3].promise;
        assert.deepEqual(controls.results.value, [{id: 'fresh-milk'}]);
        assert.equal(controls.loading.value, false);
    });
}

test('extracts validation messages from parsed and native string responses', () => {
    const payload = {errors: {barcode: ['You are offline and this barcode is not stored on this device.']}};

    assert.equal(responseErrorMessage({response: {data: payload}}, 'barcode', 'Fallback'), payload.errors.barcode[0]);
    assert.equal(responseErrorMessage({response: {data: JSON.stringify(payload)}}, 'barcode', 'Fallback'), payload.errors.barcode[0]);
    assert.equal(responseErrorMessage({response: {data: {message: 'Safe server message.'}}}, 'barcode', 'Fallback'), 'Safe server message.');
    assert.equal(responseErrorMessage(new Error('Network'), 'barcode', 'Fallback'), 'Fallback');
});

test('shows retryable search failures in Add and recipe creation', () => {
    const add = readFileSync(new URL('../resources/js/Pages/Add.vue', import.meta.url), 'utf8');
    const recipe = readFileSync(new URL('../resources/js/Components/RecipeMode.vue', import.meta.url), 'utf8');

    for (const source of [add, recipe]) {
        assert.match(source, /foodSearchUrl\([^)]*navigator\.language\)/);
        assert.match(source, /role="alert"/);
        assert.match(source, />\s*Retry\s*</);
    }

    assert.match(add, /const requestId = \+\+foodSearchRequestId/);
    assert.match(recipe, /window\.setTimeout\(\(\) => void searchFoods\(query, request\), 250\)/);
});

test('shows short-query guidance only for one non-whitespace character', () => {
    for (const path of ['../resources/js/Pages/Add.vue', '../resources/js/Components/RecipeMode.vue']) {
        const source = readFileSync(new URL(path, import.meta.url), 'utf8');
        const hint = source.match(/<p v-else-if="[^"\n]*length === 1"[^>]*>[^<]*<\/p>/)?.[0];
        assert.ok(hint);
        const render = compile(hint.replace('v-else-if', 'v-if'));

        for (const [query, visible] of [['', false], [' ', false], ['A', true], [' A ', true], ['Ab', false]] as const) {
            const vnode = render({foodSearchQuery: query.trim(), searchQuery: query}, []);
            assert.equal(vnode.type === 'p', visible);
            if (visible) assert.equal(vnode.children, 'Enter one more character to search.');
        }
    }
});
