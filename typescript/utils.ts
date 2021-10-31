interface IApiSendConfig {
    method?: 'get' | 'post' | 'put' | 'delete' | string,
    url?: string,
    params?: Record<string, unknown>,
}

interface IApiComponent {
    send?: <IResponse>(config: Record<string, unknown>) => Promise<IResponse>,
}

export const createMethod = <IRequest, IResponse = null>(
    config: IApiSendConfig,
) => (
    api: IApiComponent,
    params: IRequest = null,
    options: null,
): Promise<IResponse> => api.send<IResponse>({
    ...config,
    params,
    options,
});
