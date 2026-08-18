import { nextTick, type Ref } from 'vue';

const FOCUSABLE = 'button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [href], [tabindex]:not([tabindex="-1"])';

export function useFocusTrap(container: Ref<HTMLElement | null>, onEscape: () => void) {
    function onKeydown(event: KeyboardEvent): void {
        if (event.key === 'Escape') {
            event.preventDefault();
            onEscape();
            return;
        }

        if (event.key !== 'Tab' || !container.value) {
            return;
        }

        const focusable = Array.from(container.value.querySelectorAll<HTMLElement>(FOCUSABLE));
        const first = focusable[0];
        const last = focusable.at(-1);

        if (!first || !last) {
            return;
        }

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    function focusFirst(): void {
        nextTick(() => {
            container.value?.querySelector<HTMLElement>(FOCUSABLE)?.focus();
        });
    }

    return { onKeydown, focusFirst };
}
