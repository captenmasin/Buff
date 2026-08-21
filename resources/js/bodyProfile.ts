export const sexOptions = [
    { value: 'male', label: 'Male' },
    { value: 'female', label: 'Female' },
] as const;

export const activityLevelOptions = [
    { value: 'sedentary', label: 'Sedentary', description: 'Little or no exercise' },
    { value: 'light', label: 'Lightly active', description: 'Exercise 1–3 days a week' },
    { value: 'moderate', label: 'Moderately active', description: 'Exercise 3–5 days a week' },
    { value: 'active', label: 'Very active', description: 'Exercise 6–7 days a week' },
    { value: 'very_active', label: 'Extra active', description: 'Physical job or twice-daily training' },
] as const;

export type Sex = typeof sexOptions[number]['value'];
export type ActivityLevel = typeof activityLevelOptions[number]['value'];
