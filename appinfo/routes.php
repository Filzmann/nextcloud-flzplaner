<?php

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

        ['name' => 'api#state', 'url' => '/api/state', 'verb' => 'GET'],
        ['name' => 'api#monthPlan', 'url' => '/api/teams/{teamCode}/months/{month}', 'verb' => 'GET'],
        ['name' => 'api#transitionMonthStatus', 'url' => '/api/teams/{teamCode}/months/{month}/status', 'verb' => 'POST'],
        ['name' => 'api#saveTeamSettings', 'url' => '/api/teams/{teamCode}/settings', 'verb' => 'POST'],
        ['name' => 'api#saveDayNote', 'url' => '/api/teams/{teamCode}/months/{month}/days/{workDate}/note', 'verb' => 'POST'],
        ['name' => 'api#addShiftCandidate', 'url' => '/api/teams/{teamCode}/months/{month}/slots/{slotId}/candidates', 'verb' => 'POST', 'requirements' => ['slotId' => '\\d+']],
        ['name' => 'api#removeShiftCandidate', 'url' => '/api/teams/{teamCode}/months/{month}/slots/{slotId}/candidates/remove', 'verb' => 'POST', 'requirements' => ['slotId' => '\\d+']],
        ['name' => 'api#updateCandidateMetadata', 'url' => '/api/teams/{teamCode}/months/{month}/slots/{slotId}/candidate-metadata', 'verb' => 'POST', 'requirements' => ['slotId' => '\\d+']],
        ['name' => 'api#savePersonalWorkload', 'url' => '/api/teams/{teamCode}/personal-workload', 'verb' => 'POST'],
        ['name' => 'api#savePersonalRegularShifts', 'url' => '/api/teams/{teamCode}/personal-regular-shifts', 'verb' => 'POST'],
        ['name' => 'api#reportFixedConflict', 'url' => '/api/teams/{teamCode}/months/{month}/slots/{slotId}/fixed-conflict/report', 'verb' => 'POST', 'requirements' => ['slotId' => '\\d+']],
        ['name' => 'api#resolveFixedConflict', 'url' => '/api/teams/{teamCode}/months/{month}/slots/{slotId}/fixed-conflict/resolve', 'verb' => 'POST', 'requirements' => ['slotId' => '\\d+']],
        ['name' => 'demo_admin#install', 'url' => '/api/admin/demo-pack/install', 'verb' => 'POST'],
        ['name' => 'temporary_admin_access#status', 'url' => '/api/admin/full-access', 'verb' => 'GET'],
        ['name' => 'temporary_admin_access#activate', 'url' => '/api/admin/full-access', 'verb' => 'POST'],
        ['name' => 'temporary_admin_access#revoke', 'url' => '/api/admin/full-access/{targetUid}', 'verb' => 'DELETE'],
    ],
];
