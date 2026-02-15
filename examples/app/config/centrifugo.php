<?php

use RoadRunner\Centrifugo\Request\RequestType;

return [
    /*'services' => [
        RequestType::Connect->value => ConnectService::class,
        RequestType::Subscribe->value => SubscribeService::class,
        RequestType::Refresh->value => RefreshService::class,
        RequestType::Publish->value => PublishService::class,
        RequestType::RPC->value => RPCService::class,
    ],
    'interceptors' => [
        RequestType::Connect->value => [
            Interceptor\AuthInterceptor::class,
        ],
        RequestType::Subscribe->value => [
            Interceptor\AuthInterceptor::class,
        ],
        RequestType::RPC->value => [
            Interceptor\AuthInterceptor::class,
        ],
        '*' => [
            Interceptor\TelemetryInterceptor::class,
        ],
    ],*/
];
