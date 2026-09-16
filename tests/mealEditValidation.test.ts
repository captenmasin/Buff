import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { router, useForm } from '@inertiajs/vue3';

const source = readFileSync(new URL('../resources/js/Pages/Today.vue', import.meta.url), 'utf8');

test('allows saved hundredth-precision nutrition in both meal edit modes', () => {
    for (const [marker, values] of [
        ['editMealForm.portion_quantity', [0.15, 150.25]],
        ['editMealForm[field[0]]', [9.45, 86.25, 46.35]],
    ] as const) {
        const control = [...source.matchAll(/<Input\b[^>]*>/g)].find(([tag]) => tag.includes(marker))?.[0];
        assert.ok(control, marker);
        const step = control.match(/\bstep="([^"]+)"/)?.[1] ?? '1';
        const minimum = Number(control.match(/\bmin="([^"]+)"/)?.[1] ?? 0);

        for (const value of values) {
            const increments = (value - minimum) / Number(step);
            assert.ok(step === 'any' || Math.abs(increments - Math.round(increments)) < 1e-8, `${marker} rejects saved value ${value}`);
        }
    }
});

function handlerFor(marker: string, event: string): string {
    const control = [...source.matchAll(/<(?:Input|Button)\b[^>]*>/g)]
        .map(([tag]) => tag)
        .find((tag) => tag.includes(marker));
    const handler = control?.match(new RegExp(`@${event}="([^"]+)"`))?.[1];

    assert.ok(handler, `Missing ${event} handler for ${marker}`);

    return handler;
}

test('clears only the changed meal field error before another submission', () => {
    const cases = [
        ['name', 'v-model="editMealForm.name"', 'Corrected name'],
        ['portion_quantity', 'v-model.number="editMealForm.portion_quantity"', 0],
        ['protein_g', 'v-model.number="editMealForm[field[0]]"', 20],
        ['carbs_g', 'v-model.number="editMealForm[field[0]]"', 60],
        ['fat_g', 'v-model.number="editMealForm[field[0]]"', 20],
    ] as const;

    for (const [field, marker, value] of cases) {
        const form = useForm({ name: '', portion_quantity: 10000.1, protein_g: 1000.1, carbs_g: 1000.1, fat_g: 1000.1, date: '2026-09-04' });
        form.setError(field, 'Error for previous value');
        form.setError('date', 'Unrelated date error');
        Object.assign(form, { [field]: value });

        new Function('editMealForm', 'field', handlerFor(marker, 'update:model-value'))(form, [field]);

        assert.equal(form.errors[field], undefined, field);
        assert.equal(form.errors.date, 'Unrelated date error');
        assert.equal(form[field], value);
    }
});

test('clears the meal-type error when another meal group is selected', () => {
    const form = useForm({ meal_type: 'invalid', name: '' });
    form.setError('meal_type', 'Choose a meal');
    form.setError('name', 'Name is required');
    const marker = ':aria-pressed="editMealForm.meal_type === mealType';

    new Function('editMealForm', 'mealType', handlerFor(marker, 'click'))(form, 'lunch');

    assert.equal(form.meal_type, 'lunch');
    assert.deepEqual(form.errors, { name: 'Name is required' });
});

test('associates meal editor errors with their affected controls', () => {
    for (const field of ['name', 'portion', 'meal-type']) {
        assert.ok(source.includes(`id="meal-edit-${field}-error"`), field);
        assert.ok(source.includes(`'meal-edit-${field}-error' : undefined`), field);
    }

    assert.match(source, /:id="`meal-edit-\$\{field\[0\]\}-error`"/);
    assert.match(source, /:aria-describedby="editMealForm\.errors\[field\[0\]\] \? `meal-edit-\$\{field\[0\]\}-error` : undefined"/);
    assert.match(source, /:aria-invalid="Boolean\(editMealForm\.errors\.name\)"/);
});

test('locks all meal fields until a delayed validation response finishes, then allows correction', async (context) => {
    const editor = source.match(/<form\b[^>]*@submit\.prevent="saveMealEdit"[^>]*>([\s\S]*?)<\/form>/)?.[1];
    const fieldset = editor?.match(/^\s*<fieldset :disabled="([^"]+)" class="space-y-4">([\s\S]*)<\/fieldset>\s*$/);
    assert.ok(fieldset, 'All editor controls must be inside the processing-disabled fieldset');

    for (const control of ['editMealForm.name', 'editMealForm.portion_quantity', 'editMealForm[field[0]]', 'editMealForm.edit_mode = mode', 'editMealForm.meal_type = mealType']) {
        assert.ok(fieldset[2].includes(control), control);
    }

    const form = useForm({ name: 'Meal', portion_quantity: 10000.1, edit_mode: 'portion', meal_type: 'dinner' });
    const fieldsDisabled = () => new Function('editMealForm', `return ${fieldset[1]}`)(form);
    let finishValidation!: () => void;
    let submittedPortion: number | undefined;
    context.mock.method(router, 'put', (_url, data, options) => {
        submittedPortion = data.portion_quantity;
        options.onStart({});
        finishValidation = () => {
            options.onError({ portion_quantity: 'The portion quantity field must not be greater than 10000.' });
            options.onFinish({});
        };
    });

    assert.equal(fieldsDisabled(), false);
    form.put('/meals/meal-id');
    await Promise.resolve();

    assert.equal(fieldsDisabled(), true);
    assert.equal(form.portion_quantity, submittedPortion);
    assert.equal(form.errors.portion_quantity, undefined);

    finishValidation();

    assert.equal(fieldsDisabled(), false);
    assert.equal(form.errors.portion_quantity, 'The portion quantity field must not be greater than 10000.');
    form.portion_quantity = 100;
    new Function('editMealForm', handlerFor('v-model.number="editMealForm.portion_quantity"', 'update:model-value'))(form);
    assert.equal(form.errors.portion_quantity, undefined);
    assert.equal(form.portion_quantity, 100);
});
