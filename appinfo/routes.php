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
        ['name' => 'demo_admin#install', 'url' => '/api/admin/demo-pack/install', 'verb' => 'POST'],
    ],
];
