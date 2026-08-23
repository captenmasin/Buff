const avatarColors = [
    'bg-red-700',
    'bg-orange-700',
    'bg-amber-700',
    'bg-lime-700',
    'bg-green-700',
    'bg-teal-700',
    'bg-cyan-700',
    'bg-blue-700',
    'bg-indigo-700',
    'bg-violet-700',
    'bg-purple-700',
    'bg-fuchsia-700',
    'bg-pink-700',
    'bg-rose-700',
] as const;

export function avatarInitials(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);

    if (parts.length === 0) {
        return '?';
    }

    const initials = parts.length === 1
        ? parts[0][0]
        : `${parts[0][0]}${parts.at(-1)?.[0]}`;

    return initials.toLocaleUpperCase();
}

export function avatarColorClass(name: string): typeof avatarColors[number] {
    let seed = 0;

    for (const character of name.trim().toLocaleLowerCase()) {
        seed = (seed * 31 + (character.codePointAt(0) ?? 0)) >>> 0;
    }

    return avatarColors[seed % avatarColors.length];
}
