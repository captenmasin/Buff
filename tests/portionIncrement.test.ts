import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {stripTypeScriptTypes} from 'node:module';
import test from 'node:test';
import {computed, ref} from 'vue';

const source = readFileSync(new URL('../resources/js/Pages/Add.vue', import.meta.url), 'utf8');

test('adds one selected serving each tap and stops when the selection is cleared', () => {
    const selectedPortion = source.match(/const selectedPortion = computed\(\(\) => .*\);/)?.[0];
    const selectPortion = source.match(/function selectPortion\([^]*?\n\}/)?.[0];
    const addSelectedPortion = source.match(/function addSelectedPortion\([^]*?\n\}/)?.[0];
    assert.ok(selectedPortion);
    assert.ok(selectPortion);
    assert.ok(addSelectedPortion);
    const controls = new Function('ref', 'computed', `
        const selectedPortionKey = ref('');
        const activeFoodPortionOptions = ref([]);
        const activeFoodPortionQuantity = ref(0);
        const activeFoodPortionUnit = ref('g');
        ${stripTypeScriptTypes(`${selectedPortion}\n${selectPortion}\n${addSelectedPortion}`)}
        return {selectedPortionKey, activeFoodPortionOptions, activeFoodPortionQuantity, selectedPortion, selectPortion, addSelectedPortion};
    `)(ref, computed);
    const serving = {label: '1 serving (60g)', quantity: 60, unit: 'g'};
    controls.activeFoodPortionOptions.value = [serving];

    controls.selectPortion(serving, 0);
    controls.addSelectedPortion();
    assert.equal(controls.activeFoodPortionQuantity.value, 120);
    controls.addSelectedPortion();
    assert.equal(controls.activeFoodPortionQuantity.value, 180);

    controls.selectedPortionKey.value = '';
    controls.addSelectedPortion();
    assert.equal(controls.activeFoodPortionQuantity.value, 180);

    const fractionalServing = {label: '0.15g', quantity: 0.15, unit: 'g'};
    controls.activeFoodPortionOptions.value.push(fractionalServing);
    controls.selectPortion(fractionalServing, 1);
    controls.addSelectedPortion();
    assert.equal(controls.activeFoodPortionQuantity.value, 0.3);
});

test('shows the increment control beside the portion quantity when an option is selected', () => {
    assert.match(source, /v-if="selectedPortion"[^]*?@click="addSelectedPortion"[^]*?>\s*\+1\s*<\/Button>/);
    assert.match(source, /:disabled="Number\(activeFoodPortionQuantity\) \+ selectedPortion\.quantity > 10000"/);
    assert.match(source, /v-model\.number="activeFoodPortionQuantity"[^>]*step="any"/);
});
