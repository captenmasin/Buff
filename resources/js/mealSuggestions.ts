export type MealType = 'breakfast' | 'lunch' | 'dinner' | 'snacks';

export interface MealSuggestion {
    id: string;
    name: string;
    kind: 'entry' | 'recipe';
    meal_type: MealType;
    portion_quantity: number | null;
    portion_unit: string | null;
    protein_g: number;
}

export const mealLabels: Record<MealType, string> = {
    breakfast: 'Breakfast',
    lunch: 'Lunch',
    dinner: 'Dinner',
    snacks: 'Snacks',
};

export function mealTypeForHour(hour = new Date().getHours()): MealType {
    if (hour < 10) return 'breakfast';
    if (hour < 14) return 'lunch';
    if (hour < 20) return 'dinner';

    return 'snacks';
}

export function mealSuggestionUrl(date: string, meal: MealSuggestion): string {
    const target = meal.kind === 'recipe' ? {mode: 'recipe', recipe: meal.id} : {mode: 'food', entry: meal.id};

    return `/add?${new URLSearchParams({date, meal: meal.meal_type, ...target}).toString()}`;
}
