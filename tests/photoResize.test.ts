import assert from 'node:assert/strict';
import test from 'node:test';
import { resizePhoto } from '../resources/js/photoResize.ts';

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
