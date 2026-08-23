export function photoDataUrl(file: File): Promise<string> {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => typeof reader.result === 'string' ? resolve(reader.result) : reject(new Error('Could not read photo.'));
        reader.onerror = () => reject(reader.error ?? new Error('Could not read photo.'));
        reader.readAsDataURL(file);
    });
}
