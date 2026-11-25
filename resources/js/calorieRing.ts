export function calorieBudget(consumed: number, remaining: number, goal: number) {
    const hasTarget = goal > 0;
    const overTarget = hasTarget && remaining < 0;
    const percent = hasTarget ? Math.max(0, (consumed / goal) * 100) : 0;

    return {
        hasTarget,
        overTarget,
        amount: hasTarget ? Math.abs(remaining) : consumed,
        caption: hasTarget ? (overTarget ? 'kcal over target' : 'kcal remaining') : 'kcal eaten',
        progress: Math.min(100, percent),
        percentLabel: `${Math.round(percent)}%`,
    };
}
