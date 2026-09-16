import assert from 'node:assert/strict';
import test from 'node:test';
import axios, { type AxiosResponse } from 'axios';
import { enforceAxiosStatus } from '../resources/js/axiosStatus.ts';

function response(status: number, validateStatus: ((status: number) => boolean) | null = (value) => value >= 200 && value < 300): AxiosResponse {
    return {
        status,
        statusText: '',
        data: {},
        headers: {},
        config: { headers: {}, validateStatus } as AxiosResponse['config'],
    };
}

test('restores standard Axios status rejection for the native PHP adapter', () => {
    assert.equal(enforceAxiosStatus(response(200)).status, 200);
    assert.throws(
        () => enforceAxiosStatus(response(422)),
        (error) => axios.isAxiosError(error) && error.response?.status === 422,
    );
    assert.equal(enforceAxiosStatus(response(422, () => true)).status, 422);
});
