import assert from 'node:assert/strict';
import test from 'node:test';
import { captureVideoPhoto, resizePhoto } from '../resources/js/photoResize.ts';

test('encodes the full live camera frame as a bounded JPEG', async (t) => {
    const originalDocument = globalThis.document;
    t.after(() => { globalThis.document = originalDocument; });
    const video = {videoWidth: 1920, videoHeight: 1080} as HTMLVideoElement;
    const drawCalls: unknown[][] = [];
    globalThis.document = {
        createElement: () => ({
            getContext: () => ({
                fillRect() {},
                drawImage(...args: unknown[]) { drawCalls.push(args); },
            }),
            toBlob(callback: BlobCallback, type: string) {
                callback(new Blob(['frame'], {type}));
            },
        }),
    } as unknown as Document;

    const photo = await captureVideoPhoto(video);

    assert.deepEqual(drawCalls, [[video, 0, 0, 1600, 900]]);
    assert.equal(photo.type, 'image/jpeg');
    await assert.rejects(captureVideoPhoto({videoWidth: 0, videoHeight: 0} as HTMLVideoElement), /Camera is not ready/);
});

test('rejects original photos when JPEG preparation fails', async (t) => {
    const originalCreateImageBitmap = globalThis.createImageBitmap;
    const originalDocument = globalThis.document;
    const file = new File(['Exif\0\0private-location'], 'photo.jpg', {type: 'image/jpeg'});
    const bitmap = {width: 100, height: 100, close() {}} as ImageBitmap;
    const context = {fillStyle: '', fillRect() {}, drawImage() {}} as unknown as CanvasRenderingContext2D;

    t.after(() => Object.assign(globalThis, {
        createImageBitmap: originalCreateImageBitmap,
        document: originalDocument,
    }));

    await t.test('image decoding rejects', async () => {
        globalThis.createImageBitmap = async () => {
            throw new Error('Could not decode photo.');
        };

        await assert.rejects(resizePhoto(file), /Could not decode photo\./);
    });

    await t.test('canvas context is unavailable', async () => {
        globalThis.createImageBitmap = async () => bitmap;
        globalThis.document = {createElement: () => ({getContext: () => null})} as unknown as Document;

        await assert.rejects(resizePhoto(file), /Could not prepare photo\./);
    });

    await t.test('JPEG encoding returns no blob', async () => {
        globalThis.createImageBitmap = async () => bitmap;
        globalThis.document = {
            createElement: () => ({
                getContext: () => context,
                toBlob: (callback: BlobCallback) => callback(null),
            }),
        } as unknown as Document;

        await assert.rejects(resizePhoto(file), /Could not prepare photo\./);
    });
});
