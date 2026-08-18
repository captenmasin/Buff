import { type InjectionKey, type Ref, type ShallowRef } from 'vue';

export interface SelectItemRegistration {
    value: string;
    label: string;
    disabled: boolean;
}

export interface SelectContext {
    root: ShallowRef<HTMLElement | null>;
    model: Ref<string | number | null | undefined>;
    open: Ref<boolean>;
    disabled: Ref<boolean>;
    items: Ref<SelectItemRegistration[]>;
    highlightedValue: Ref<string | null>;
    registerItem: (item: SelectItemRegistration) => void;
    unregisterItem: (value: string) => void;
    selectItem: (value: string) => void;
    close: () => void;
    toggle: () => void;
}

export const SELECT_CONTEXT_KEY: InjectionKey<SelectContext> = Symbol('select');
