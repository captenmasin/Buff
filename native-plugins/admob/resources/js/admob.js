let endpoint = '/_admob/call';

export const Events = {
    AdLoaded: 'BlessedZulu\\NativePhpAdmob\\Events\\AdLoaded',
    AdFailedToLoad: 'BlessedZulu\\NativePhpAdmob\\Events\\AdFailedToLoad',
    AdShown: 'BlessedZulu\\NativePhpAdmob\\Events\\AdShown',
    AdFailedToShow: 'BlessedZulu\\NativePhpAdmob\\Events\\AdFailedToShow',
    ConsentInfoUpdated: 'BlessedZulu\\NativePhpAdmob\\Events\\ConsentInfoUpdated',
    ConsentFormDismissed: 'BlessedZulu\\NativePhpAdmob\\Events\\ConsentFormDismissed',
    PrivacyOptionsFormDismissed: 'BlessedZulu\\NativePhpAdmob\\Events\\PrivacyOptionsFormDismissed',
    TrackingAuthorizationGranted: 'BlessedZulu\\NativePhpAdmob\\Events\\TrackingAuthorizationGranted',
    TrackingAuthorizationDenied: 'BlessedZulu\\NativePhpAdmob\\Events\\TrackingAuthorizationDenied',
};

export function setEndpoint(url) {
    endpoint = url;
}

function enqueue(task) {
    const queue = (typeof window !== 'undefined' && window.__admobCallQueue) || Promise.resolve();
    const run = queue.then(task, task);

    if (typeof window !== 'undefined') {
        window.__admobCallQueue = run.catch(() => {});
    }

    return run;
}

function call(body) {
    return enqueue(async () => {
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(body),
            });
            const result = await response.json().catch(() => ({}));

            return response.ok
                ? {ok: result.ok !== false, ...result}
                : {ok: false, error: result.error || `http_${response.status}`};
        } catch {
            return {ok: false, error: 'network_error'};
        }
    });
}

function normalizePayload(payload) {
    if (typeof payload === 'string') {
        try {
            return normalizePayload(JSON.parse(payload));
        } catch {
            return {};
        }
    }

    return payload !== null && typeof payload === 'object' ? payload : {};
}

async function waitForNativeResult(outcomes, start, matches = () => true) {
    const {On, Off} = await import('#nativephp');

    return new Promise((resolve) => {
        let settled = false;
        const listeners = Object.entries(outcomes).map(([event, succeeded]) => {
            const listener = (payload) => {
                const result = normalizePayload(payload);

                if (matches(result)) {
                    finish({ok: succeeded && result.success !== false, ...result});
                }
            };

            On(event, listener);
            return {event, listener};
        });
        const timeout = setTimeout(() => finish({ok: false, error: 'native_event_timeout'}), 120_000);

        function finish(result) {
            if (settled) {
                return;
            }

            settled = true;
            clearTimeout(timeout);
            listeners.forEach(({event, listener}) => Off(event, listener));
            resolve(result);
        }

        Promise.resolve(start()).then((result) => {
            if (!result.ok) {
                finish(result);
            }
        }).catch(() => finish({ok: false, error: 'native_call_failed'}));
    });
}

export const Admob = {
    enabled: () => call({kind: 'lifecycle', action: 'enabled'}).then((result) => result.enabled === true),
    configurePolicy: (policy) => call({
        kind: 'lifecycle',
        action: 'configurePolicy',
        under_age_of_consent: policy.underAgeOfConsent,
        non_personalized: policy.nonPersonalized,
        max_content_rating: policy.maxContentRating,
    }),
    initialize: () => call({kind: 'lifecycle', action: 'initialize'}),
    banner: (slot) => ({
        load: () => waitForNativeResult(
            {[Events.AdLoaded]: true, [Events.AdFailedToLoad]: false},
            () => call({kind: 'ad', format: 'banner', slot, action: 'load'}),
            (payload) => payload.slot === slot && payload.format === 'banner',
        ),
        show: (position = 'bottom', offset = null) => waitForNativeResult(
            {[Events.AdShown]: true, [Events.AdFailedToShow]: false},
            () => call({
                kind: 'ad',
                format: 'banner',
                slot,
                action: 'show',
                position,
                ...(offset == null ? {} : {offset}),
            }),
            (payload) => payload.slot === slot && payload.format === 'banner',
        ),
        hide: () => call({kind: 'ad', format: 'banner', slot, action: 'hide'}),
    }),
    ump: {
        requestInfo: () => waitForNativeResult(
            {[Events.ConsentInfoUpdated]: true},
            () => call({kind: 'ump', action: 'requestInfo'}),
        ),
        showForm: () => waitForNativeResult(
            {[Events.ConsentFormDismissed]: true},
            () => call({kind: 'ump', action: 'showForm'}),
        ),
        canRequestAds: () => call({kind: 'ump', action: 'canRequestAds'}).then((result) => result.can_request === true),
        status: () => call({kind: 'ump', action: 'status'}).then((result) => result.status ?? 'unknown'),
        privacyOptionsStatus: () => call({kind: 'ump', action: 'privacyOptionsStatus'})
            .then((result) => result.status ?? 'unknown'),
        showPrivacyOptionsForm: () => waitForNativeResult(
            {[Events.PrivacyOptionsFormDismissed]: true},
            () => call({kind: 'ump', action: 'showPrivacyOptionsForm'}),
        ),
    },
    att: {
        request: () => waitForNativeResult(
            {
                [Events.TrackingAuthorizationGranted]: true,
                [Events.TrackingAuthorizationDenied]: true,
            },
            () => call({kind: 'att', action: 'request'}),
        ),
        status: () => call({kind: 'att', action: 'status'}).then((result) => result.status ?? 'unsupported'),
    },
};

export default Admob;
