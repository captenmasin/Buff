import assert from 'node:assert/strict';
import test from 'node:test';
import {
    overlayPhotoForPose,
    poseSortKey,
    progressPhotoCaptureLabels,
    progressPhotoPoses,
    selectPoseOverlays,
    sortProgressPhotos,
} from '../resources/js/progressPhotos.ts';

test('orders front, side, then back and leaves unlabeled photos last', () => {
    assert.deepEqual([...progressPhotoPoses], ['front', 'side', 'back']);
    assert.equal(progressPhotoCaptureLabels.front, 'Take front');
    assert.equal(poseSortKey('front'), 0);
    assert.equal(poseSortKey('side'), 1);
    assert.equal(poseSortKey('back'), 2);
    assert.equal(poseSortKey(null), 99);

    const sorted = sortProgressPhotos([
        { id: '2', pose: 'back' },
        { id: '0', pose: null },
        { id: '1', pose: 'front' },
        { id: '3', pose: 'side' },
    ]);

    assert.deepEqual(sorted.map((photo) => photo.id), ['1', '3', '2', '0']);
});

test('ghosts a matching pose and does not borrow a different labeled pose', () => {
    const photos = [
        { id: 'front', pose: 'front' },
        { id: 'back', pose: 'back' },
    ];

    assert.equal(overlayPhotoForPose(photos, 'front')?.id, 'front');
    assert.equal(overlayPhotoForPose(photos, 'side'), null);
    assert.equal(overlayPhotoForPose(photos, 'back')?.id, 'back');
});

test('falls back to photo order only when no poses are labeled', () => {
    const photos = [{ id: 'first', pose: null }, { id: 'second', pose: null }];

    assert.equal(overlayPhotoForPose(photos, 'front')?.id, 'first');
    assert.equal(overlayPhotoForPose(photos, 'side')?.id, 'second');
    assert.equal(overlayPhotoForPose(photos, 'back'), null);
});

test('ghosts each pose from the latest other day that has that pose', () => {
    const overlays = selectPoseOverlays([
        {
            date: '2026-08-20',
            photos: [
                { id: 'today-front', pose: 'front' },
                { id: 'today-back', pose: 'back' },
            ],
        },
        {
            date: '2026-08-10',
            photos: [
                { id: 'older-front', pose: 'front' },
                { id: 'older-side', pose: 'side' },
            ],
        },
    ], '2026-08-21');

    assert.equal(overlays.front?.photo.id, 'today-front');
    assert.equal(overlays.front?.date, '2026-08-20');
    assert.equal(overlays.side?.photo.id, 'older-side');
    assert.equal(overlays.side?.date, '2026-08-10');
    assert.equal(overlays.back?.photo.id, 'today-back');
    assert.equal(overlays.back?.date, '2026-08-20');
});

test('prefers another day over the current day when retaking photos', () => {
    const overlays = selectPoseOverlays([
        {
            date: '2026-08-20',
            photos: [{ id: 'same-front', pose: 'front' }],
        },
        {
            date: '2026-08-10',
            photos: [{ id: 'older-front', pose: 'front' }],
        },
    ], '2026-08-20');

    assert.equal(overlays.front?.photo.id, 'older-front');
    assert.equal(overlays.front?.date, '2026-08-10');
});
