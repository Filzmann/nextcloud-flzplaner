<?php
declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\PublicApi\V1 {
    interface PermissionProvider { public function descriptor(): PermissionProviderDescriptor; public function collect(): PermissionProviderResult; }
    final class PermissionProviderDescriptor { public function __construct(...$args) {} }
    final class PermissionCondition { private function __construct(public string $operator, public ?string $groupId=null, public array $children=[]) {} public static function group(string $id):self{return new self('group',$id);} public static function all(array $c):self{return new self('all',null,$c);} public static function any(array $c):self{return new self('any',null,$c);} public static function self():self{return new self('self');} public static function authenticated():self{return new self('authenticated');} public static function nextcloudAdmin():self{return new self('nextcloud-admin');} }
    final class PermissionRule { public function __construct(public string $type,public string $name,public string $detail,public string $permission,public string $label,public string $effect,public string $scope,public PermissionCondition $condition,public string $source,public string $confidence){} }
    final class PermissionProviderResult { public function __construct(public array $rules,public bool $complete=true,public array $warnings=[]){} }
    final class RegisterPermissionProvidersEvent { public array $providers=[]; public function register(PermissionProvider $p):void{$this->providers[]=$p;} }
}
namespace {
    require_once dirname(__DIR__, 2) . '/lib/Permission/PlanerPermissionSourceInterface.php';
    require_once dirname(__DIR__, 2) . '/lib/Permission/PlanerPermissionProvider.php';
    require_once dirname(__DIR__, 2) . '/lib/Permission/PlanerPermissionProviderListener.php';

    use OCA\AdPlaner\Permission\PlanerPermissionProvider;
    use OCA\AdPlaner\Permission\PlanerPermissionProviderListener;
    use OCA\AdPlaner\Permission\PlanerPermissionSourceInterface;
    use OCA\FilzmannPermissionMatrix\PublicApi\V1\RegisterPermissionProvidersEvent;
    $source=new class implements PlanerPermissionSourceInterface { public function teamGroupIds():array{return ['ad-ASN-A','ad-ASN-B'];} public function ebGroupId():string{return 'ad-EB';} };
    $provider=new PlanerPermissionProvider($source); $result=$provider->collect(); $by=[]; foreach($result->rules as $rule)$by[$rule->permission][]=$rule;
    if(count($by['plan.team.read']??[])!==2)throw new RuntimeException('Jedes vorhandene Team braucht eine Leseregel.');
    $coordinate=$by['plan.team.coordinate'][0]??null;
    if($coordinate?->condition->operator!=='all'||array_map(fn($c)=>$c->groupId,$coordinate->condition->children)!==['ad-ASN-A','ad-EB'])throw new RuntimeException('Koordination muss Team UND EB verlangen.');
    if(($by['plan.assignment.manage-own'][0]->condition->operator??null)!=='all')throw new RuntimeException('Eigene Zuweisungen müssen zusätzlich an das Team gebunden bleiben.');
    $event=new RegisterPermissionProvidersEvent();(new PlanerPermissionProviderListener($provider))->handle($event);if(($event->providers[0]??null)!==$provider)throw new RuntimeException('Lazy-Registrierung fehlt.');
    echo "Planer permission provider tests passed\n";
}
