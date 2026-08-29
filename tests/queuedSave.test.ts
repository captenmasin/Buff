import assert from 'node:assert/strict';
import test from 'node:test';
import {type QueuedSaveCallbacks, useQueuedSave} from '../resources/js/useQueuedSave.ts';

test('saves the latest change after the active save finishes', () => {
    const requests: QueuedSaveCallbacks[] = [];
    const {save, status} = useQueuedSave((callbacks) => requests.push(callbacks));

    save();
    save();

    assert.equal(requests.length, 1);
    assert.equal(status.value, 'saving');

    requests[0].onSuccess();
    requests[0].onFinish();

    assert.equal(requests.length, 2);
    assert.equal(status.value, 'saving');

    requests[1].onSuccess();
    requests[1].onFinish();

    assert.equal(status.value, 'saved');
});

test('shows a local error when a save fails', () => {
    let request: QueuedSaveCallbacks | undefined;
    const {save, status} = useQueuedSave((callbacks) => {
        request = callbacks;
    });

    save();
    request?.onError();
    request?.onFinish();

    assert.equal(status.value, 'error');
});
