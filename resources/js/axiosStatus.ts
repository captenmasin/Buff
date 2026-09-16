import axios, { type AxiosResponse } from 'axios';

export function enforceAxiosStatus<T>(response: AxiosResponse<T>): AxiosResponse<T> {
    const validateStatus = response.config.validateStatus;

    if (validateStatus && !validateStatus(response.status)) {
        throw new axios.AxiosError(
            `Request failed with status code ${response.status}`,
            response.status >= 500 ? axios.AxiosError.ERR_BAD_RESPONSE : axios.AxiosError.ERR_BAD_REQUEST,
            response.config,
            response.request,
            response,
        );
    }

    return response;
}
