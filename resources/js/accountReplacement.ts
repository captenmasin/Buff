export type SocialProvider = 'google' | 'apple';

export function accountReplacementDecision(hasLocalAccount: boolean, provider: SocialProvider) {
    return { type: hasLocalAccount ? 'confirm' as const : 'launch' as const, provider };
}
