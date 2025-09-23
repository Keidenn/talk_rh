<?php
return [
    'routes' => [
        // Page principale de ton app (celle qui s'ouvre depuis le menu)
        ['name' => 'page#index', 'url' => '/page', 'verb' => 'GET'],
        // Vue employé accessible explicitement (utile pour les admins aussi)
        ['name' => 'page#employeeView', 'url' => '/page/employee', 'verb' => 'GET'],

        // Employee API
        ['name' => 'api#getMyLeaves', 'url' => '/api/leaves', 'verb' => 'GET'],
        ['name' => 'api#createLeave', 'url' => '/api/leaves', 'verb' => 'POST'],
        ['name' => 'api#deleteLeave', 'url' => '/api/leaves/{id}', 'verb' => 'DELETE'],

        // Admin API
        ['name' => 'api#getAllLeaves', 'url' => '/api/admin/leaves', 'verb' => 'GET'],
        ['name' => 'api#setLeaveStatus', 'url' => '/api/admin/leaves/{id}/status', 'verb' => 'POST'],

        // Settings (admin page renders via ISettings; this endpoint saves selection)
        ['name' => 'api#saveAdminGroup', 'url' => '/api/admin/settings/group', 'verb' => 'POST'],
        ['name' => 'api#getAdminGroup', 'url' => '/api/admin/settings/group', 'verb' => 'GET'],
        ['name' => 'api#listGroups', 'url' => '/api/admin/settings/groups', 'verb' => 'GET'],
        ['name' => 'api#getAdminGroupMembers', 'url' => '/api/admin/settings/group/members', 'verb' => 'GET'],

        // ICS feed (Calendar)
        ['name' => 'ics#feed', 'url' => '/ics/{uid}/{token}', 'verb' => 'GET'],
        ['name' => 'ics#getToken', 'url' => '/api/ics/token', 'verb' => 'GET'],
        ['name' => 'ics#regenToken', 'url' => '/api/ics/token', 'verb' => 'POST'],
    ],
];
