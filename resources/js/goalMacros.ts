export type MacroSplit = {
    protein: number;
    carbs: number;
    fat: number;
};

export type MacroGrams = {
    protein: number;
    carbs: number;
    fat: number;
};

export const macroPresets = Object.freeze([
    Object.freeze({ protein: 30, carbs: 40, fat: 30 }),
    Object.freeze({ protein: 40, carbs: 30, fat: 30 }),
    Object.freeze({ protein: 30, carbs: 30, fat: 40 }),
] as const);

function roundToTwo(value: number): number {
    return Number(value.toFixed(2));
}

function snapToFive(value: number): number {
    return Math.round(value / 5) * 5;
}

export function normalizeSplit(split: Partial<MacroSplit>): MacroSplit {
    const protein = Math.max(0, Math.min(100, snapToFive(Number(split.protein) || 0)));
    const carbs = Math.max(0, Math.min(100 - protein, snapToFive(Number(split.carbs) || 0)));

    return { protein, carbs, fat: 100 - protein - carbs };
}

export function splitFromGrams(calories: number, grams: MacroGrams): MacroSplit {
    if (!Number.isFinite(calories) || calories <= 0) {
        return normalizeSplit({});
    }

    return normalizeSplit({
        protein: (grams.protein * 4 / calories) * 100,
        carbs: (grams.carbs * 4 / calories) * 100,
    });
}

export function gramsForSplit(calories: number, split: MacroSplit): MacroGrams {
    const normalized = normalizeSplit(split);
    const protein = roundToTwo((calories * normalized.protein / 100) / 4);
    const carbs = roundToTwo((calories * normalized.carbs / 100) / 4);
    const fat = roundToTwo((calories - (protein * 4) - (carbs * 4)) / 9);

    return { protein, carbs, fat };
}

export function macroCalories(grams: MacroGrams): number {
    return Math.round((grams.protein * 4) + (grams.carbs * 4) + (grams.fat * 9));
}

export function splitWithinGramBounds(calories: number, split: MacroSplit): boolean {
    return Object.values(gramsForSplit(calories, split)).every((grams) => grams >= 0 && grams <= 1000);
}

export function hasValidFivePercentSplit(calories: number): boolean {
    for (let protein = 0; protein <= 100; protein += 5) {
        for (let carbs = 0; carbs <= 100 - protein; carbs += 5) {
            if (splitWithinGramBounds(calories, { protein, carbs, fat: 100 - protein - carbs })) {
                return true;
            }
        }
    }

    return false;
}
