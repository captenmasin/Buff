export async function resizePhoto(file: File): Promise<File> {
    const image = await createImageBitmap(file);

    try {
        return await prepareJpeg(image, image.width, image.height, file.name.replace(/\.[^.]+$/, '') + '.jpg');
    } finally {
        image.close();
    }
}

export async function captureVideoPhoto(video: HTMLVideoElement): Promise<File> {
    if (!video.videoWidth || !video.videoHeight) {
        throw new Error('Camera is not ready.');
    }

    return prepareJpeg(video, video.videoWidth, video.videoHeight, 'nutrition-label.jpg');
}

async function prepareJpeg(image: CanvasImageSource, width: number, height: number, filename: string): Promise<File> {
    const scale = Math.min(1, 1600 / Math.max(width, height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(width * scale));
    canvas.height = Math.max(1, Math.round(height * scale));
    const context = canvas.getContext('2d');

    if (!context) {
        throw new Error('Could not prepare photo.');
    }

    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.drawImage(image, 0, 0, canvas.width, canvas.height);
    const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.82));

    if (!blob) {
        throw new Error('Could not prepare photo.');
    }

    return new File([blob], filename, {type: 'image/jpeg'});
}
