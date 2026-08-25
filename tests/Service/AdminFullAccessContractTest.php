<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);$template=(string)file_get_contents($root.'/templates/admin.php');$script=(string)file_get_contents($root.'/js/admin.js');$routes=(string)file_get_contents($root.'/appinfo/routes.php');$migration=(string)file_get_contents($root.'/lib/Migration/Version000005Date202608250001.php');$service=(string)file_get_contents($root.'/lib/Service/TemporaryAdminAccessService.php');
foreach(['adp-full-access-form','adp-full-access-enabled','adp-full-access-history','value="1440"']as$value)if(!str_contains($template,$value))throw new RuntimeException('Adminfreigabe-UI fehlt: '.$value);
foreach(['/api/admin/full-access','durationMinutes','targetUid','Widerrufen']as$value)if(!str_contains($script.$routes,$value))throw new RuntimeException('Adminfreigabe-API fehlt: '.$value);
foreach(['adp_admin_access','target_uid','granted_by','starts_at','ends_at','revoked_at','revoked_by','created_at']as$value)if(!str_contains($migration,$value))throw new RuntimeException('Adminfreigabe-Migration fehlt: '.$value);
foreach(['MAX_DURATION_MINUTES=1440','$durationMinutes>self::MAX_DURATION_MINUTES','isAdmin($uid)','activeFor($uid']as$value)if(!str_contains(str_replace(' ','',$service),str_replace(' ','',$value)))throw new RuntimeException('Adminfreigabe-Sicherheitsgrenze fehlt: '.$value);
echo "AdPlaner admin full access contract tests passed\n";
