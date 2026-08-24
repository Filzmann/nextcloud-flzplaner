<?php
declare(strict_types=1);
namespace OCA\AdPlaner\Permission;
use OCA\FilzmannPermissionMatrix\PublicApi\V1\{PermissionCondition,PermissionProvider,PermissionProviderDescriptor,PermissionProviderResult,PermissionRule};
final class PlanerPermissionProvider implements PermissionProvider {
    public function __construct(private PlanerPermissionSourceInterface $source) {}
    public function descriptor():PermissionProviderDescriptor{return new PermissionProviderDescriptor('adplaner','AD Planer','1.0',['permissions']);}
    public function collect():PermissionProviderResult{
        $rules=[];$eb=$this->source->ebGroupId();
        foreach($this->source->teamGroupIds() as $team){
            $rules[]=$this->rule('Monatsplan',$team,'Teamplan lesen','plan.team.read','Lesen','team:'.$team,PermissionCondition::group($team));
            $rules[]=$this->rule('Schichtzuweisung',$team,'Eigene Wünsche im Team','plan.assignment.manage-own','Eigene Zuweisung','team:'.$team,PermissionCondition::all([PermissionCondition::group($team),PermissionCondition::self()]));
            $condition=PermissionCondition::all([PermissionCondition::group($team),PermissionCondition::group($eb)]);
            $rules[]=$this->rule('Monatsplan',$team,'Fremdzuweisungen und Teamkonfiguration','plan.team.coordinate','Koordinieren','team:'.$team,$condition);
            $rules[]=$this->rule('Monatsplan',$team,'Status planned/approved ändern','plan.status.transition','Status ändern','team:'.$team,$condition);
        }
        $rules[]=$this->rule('Administration','Demo-Pack','Nur Nextcloud-Administration','plan.demo.manage','Demo verwalten','app',PermissionCondition::nextcloudAdmin());
        return new PermissionProviderResult($rules);
    }
    private function rule(string $type,string $name,string $detail,string $key,string $label,string $scope,PermissionCondition $condition):PermissionRule{return new PermissionRule($type,$name,$detail,$key,$label,'allow',$scope,$condition,'adplaner:TeamAccessService','high');}
}
