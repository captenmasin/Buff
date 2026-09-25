export function calorieRingStrokeClass(consumed: number, goal: number): string {
    if (goal > 0 && consumed > goal) {
        return 'stroke-destructive';
    }

    return 'stroke-success';
}

export function calorieRemainingLabel(remaining: number, goal: number): string {
    if (goal <= 0) {
        return 'No daily target yet';
    }

    const amount = Math.abs(Math.round(remaining));

    if (remaining < 0) {
        return `You're ${amount} over`;
    }

    return `${amount} left of ${goal}`;
}

export function calorieRemainingAriaLabel(consumed: number, remaining: number, goal: number): string {
    if (goal <= 0) {
        return `${consumed} calories, no daily target yet`;
    }

    if (remaining < 0) {
        return `${consumed} of ${goal} calories, ${Math.abs(Math.round(remaining))} over`;
    }

    return `${consumed} of ${goal} calories, ${Math.round(remaining)} remaining`;
}
