interface Nutrition {
    calories: number | null;
    protein_g: number;
    carbs_g: number;
    fat_g: number;
}

export function scaleLabelNutrition(nutrition: Nutrition, portion: number, labelPortion: number): Nutrition {
    const factor = Math.max(0, portion) / labelPortion;

    return {
        calories: nutrition.calories === null ? null : Math.round(nutrition.calories * factor),
        protein_g: Math.round(nutrition.protein_g * factor * 100) / 100,
        carbs_g: Math.round(nutrition.carbs_g * factor * 100) / 100,
        fat_g: Math.round(nutrition.fat_g * factor * 100) / 100,
    };
}
