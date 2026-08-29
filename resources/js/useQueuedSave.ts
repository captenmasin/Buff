import {ref, type Ref} from 'vue';

export type LocalSaveStatus = 'idle' | 'saving' | 'saved' | 'error';

export interface QueuedSaveCallbacks {
    onSuccess: () => void;
    onError: () => void;
    onFinish: () => void;
}

export function useQueuedSave(submit: (callbacks: QueuedSaveCallbacks) => void): {
    save: () => void;
    status: Ref<LocalSaveStatus>;
} {
    const status = ref<LocalSaveStatus>('idle');
    let processing = false;
    let queued = false;

    function save(): void {
        if (processing) {
            queued = true;

            return;
        }

        processing = true;
        status.value = 'saving';
        submit({
            onSuccess: () => {
                status.value = 'saved';
            },
            onError: () => {
                status.value = 'error';
            },
            onFinish: () => {
                processing = false;

                if (queued) {
                    queued = false;
                    save();
                }
            },
        });
    }

    return {save, status};
}
