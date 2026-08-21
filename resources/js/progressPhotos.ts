export const progressPhotoPoses = ['front', 'side', 'back'] as const;

export type ProgressPhotoPose = typeof progressPhotoPoses[number];

export const progressPhotoLabels: Record<ProgressPhotoPose, string> = {
    front: 'Front',
    side: 'Side',
    back: 'Back',
};

export const progressPhotoCaptureLabels: Record<ProgressPhotoPose, string> = {
    front: 'Take front',
    side: 'Take side',
    back: 'Take back',
};

export function isProgressPhotoPose(value: unknown): value is ProgressPhotoPose {
    return progressPhotoPoses.includes(value as ProgressPhotoPose);
}

export function poseSortKey(pose: string | null | undefined): number {
    const index = progressPhotoPoses.indexOf(pose as ProgressPhotoPose);

    return index === -1 ? 99 : index;
}

export function sortProgressPhotos<T extends { pose?: string | null }>(photos: T[]): T[] {
    return [...photos].sort((left, right) => poseSortKey(left.pose) - poseSortKey(right.pose));
}

export function overlayPhotoForPose<T extends { pose?: string | null }>(photos: T[], pose: ProgressPhotoPose): T | null {
    const labeled = photos.find((photo) => photo.pose === pose);

    if (labeled) {
        return labeled;
    }

    if (photos.some((photo) => isProgressPhotoPose(photo.pose))) {
        return null;
    }

    return photos[progressPhotoPoses.indexOf(pose)] ?? null;
}

export function selectPoseOverlays<T extends { pose?: string | null }>(
    metrics: Array<{ date: string; photos: T[] }>,
    currentDate: string,
): Partial<Record<ProgressPhotoPose, { photo: T; date: string }>> {
    const byRecency = [...metrics].sort((left, right) => right.date.localeCompare(left.date));
    const ordered = [
        ...byRecency.filter((metric) => metric.date !== currentDate),
        ...byRecency.filter((metric) => metric.date === currentDate),
    ];
    const overlays: Partial<Record<ProgressPhotoPose, { photo: T; date: string }>> = {};

    for (const pose of progressPhotoPoses) {
        for (const metric of ordered) {
            const photo = overlayPhotoForPose(metric.photos, pose);

            if (photo) {
                overlays[pose] = { photo, date: metric.date };
                break;
            }
        }
    }

    return overlays;
}
