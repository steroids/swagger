# Swagger API generator

Генерирует карту API в виде swagger.json-файла, и дает возможность просмотреть его
при помощи [ReDoc](https://github.com/Redocly/redoc) просмотрщика.

### Установка

1. Прописать в конфигурации приложения модуль DocsModule, например:
    ```
    [
        ...
        'modules' => [
            ...
            'docs' => [
                'class' => 'steroids\docs\DocsModule',
            ],
        ],
    ]
    ```
2. В Gii разрешить в RBAC доступ к URL модуля (по-умолчанию `docs`). 