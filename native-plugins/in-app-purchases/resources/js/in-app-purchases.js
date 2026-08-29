const baseUrl = '/_native/api/call';

async function bridgeCall(method, params = {}) {
    const response = await fetch(baseUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        body: JSON.stringify({method, params}),
    });
    const result = await response.json();

    if (!response.ok || result.status === 'error') {
        throw new Error(result.message || 'Native subscription call failed.');
    }

    return result.data;
}

export function configure(apiKey, appUserId) {
    return bridgeCall('Subscriptions.Configure', {api_key: apiKey, app_user_id: appUserId});
}

export function loadOffering() {
    return bridgeCall('Subscriptions.LoadOffering');
}

export function purchase(packageIdentifier) {
    return bridgeCall('Subscriptions.Purchase', {package_identifier: packageIdentifier});
}

export function restore() {
    return bridgeCall('Subscriptions.Restore');
}

export function customerInfo() {
    return bridgeCall('Subscriptions.CustomerInfo');
}

export const subscriptions = {configure, loadOffering, purchase, restore, customerInfo};

export default subscriptions;
