export function calorieRingStrokeClass(consumed: number, goal: number): string {
    if (goal > 0 && consumed > goal) {
        return 'stroke-destructive';
    }

    return 'stroke-success';
}
