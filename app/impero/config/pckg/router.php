<?php

return [
    'publicRoutes' => [
        // derive
        'derive.user.profile',
        'api.auth.user',
        'dynamic.records.field.toggle',
        'dynamic.records.field.order',
        // impero
        'api.(.*)',
        'api.services.install',
        'impero.servers.addServer',
        'impero.servers.refreshServersServiceStatus',
        'impero.servers.refreshServersDependencyStatus',
    ],
];