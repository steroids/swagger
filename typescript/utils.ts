interface IApiSendConfig {
    method?: 'get' | 'post' | 'put' | 'delete' | string,
    url?: string,
    params?: Record<string, unknown>,
}

interface IResponseWrapper<IResponse> {
    data: IResponse,
    status?: number,
    headers?: Record<string, unknown>,
    [key: string]: unknown,
}

interface IApiComponent {
    send?: <IResponse>(config: Record<string, unknown>) => Promise<IResponseWrapper<IResponse>>,
}

export const createMethod = <IRequest, IResponse = null>(
    config: IApiSendConfig,
) => (
    api: IApiComponent,
    params: IRequest = null,
    options: Record<string, unknown> = null,
): Promise<IResponseWrapper<IResponse>> => api.send<IResponse>({
    ...config,
    params,
    options,
});
