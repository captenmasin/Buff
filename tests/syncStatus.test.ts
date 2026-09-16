import assert from 'node:assert/strict';
import test from 'node:test';
import {buffSyncStatus} from '../resources/js/syncStatus.ts';

const completeState = {
    last_succeeded_at: '2026-09-02T12:00:00Z',
    last_error: 'Network unavailable.',
    pending: 2,
};

test('prioritizes active, failed, pending, and successful sync states', () => {
    assert.equal(buffSyncStatus(completeState, true)?.kind, 'syncing');
    assert.deepEqual(buffSyncStatus(completeState, false), {
        kind: 'failed',
        detail: 'Sync failed: Network unavailable.',
        retry: true,
    });
    assert.deepEqual(buffSyncStatus({...completeState, last_error: null}, false), {
        kind: 'pending',
        detail: '2 changes waiting to sync.',
        retry: true,
    });
    assert.equal(buffSyncStatus({...completeState, last_error: null, pending: 0}, false)?.kind, 'synced');
});

test('uses singular pending copy and hides absent sync state', () => {
    assert.equal(buffSyncStatus({pending: 1}, false)?.detail, '1 change waiting to sync.');
    assert.equal(buffSyncStatus(null, false), null);
});
