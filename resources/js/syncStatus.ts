export interface BuffSyncState {
    last_succeeded_at?: string | null;
    last_error?: string | null;
    pending?: number | null;
}

export interface BuffSyncStatus {
    kind: 'syncing' | 'failed' | 'pending' | 'synced';
    detail: string;
    retry: boolean;
}

export function buffSyncStatus(state: BuffSyncState | null | undefined, syncing: boolean): BuffSyncStatus | null {
    if (!state) {
        return null;
    }

    if (syncing) {
        return {kind: 'syncing', detail: 'Syncing changes…', retry: false};
    }

    if (state.last_error) {
        return {kind: 'failed', detail: `Sync failed: ${state.last_error}`, retry: true};
    }

    const pending = Math.max(0, Math.trunc(state.pending ?? 0));

    if (pending > 0) {
        return {
            kind: 'pending',
            detail: `${pending} ${pending === 1 ? 'change' : 'changes'} waiting to sync.`,
            retry: true,
        };
    }

    if (state.last_succeeded_at) {
        return {
            kind: 'synced',
            detail: `Last synced ${new Date(state.last_succeeded_at).toLocaleString([], {dateStyle: 'short', timeStyle: 'short'})}`,
            retry: false,
        };
    }

    return null;
}
