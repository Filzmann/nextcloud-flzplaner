<?php
declare(strict_types=1);
namespace OCA\FlzPlaner\Permission;
use OCA\FlzPermissionMatrix\PublicApi\V1\{PermissionCondition,PermissionProvider,PermissionProviderDescriptor,PermissionProviderResult,PermissionRule};
final class PlanerPermissionProvider implements PermissionProvider {
    public function __construct(private PlanerPermissionSourceInterface $source) {}
    public function descriptor():PermissionProviderDescriptor{return new PermissionProviderDescriptor('flzplaner','Filzmann Assistenzplanung','1.0',['permissions']);}
    public function collect():PermissionProviderResult{
        $rules=[];$eb=$this->source->ebGroupId();
        foreach($this->source->teamGroupIds() as $team){
            $rules[]=$this->rule('Monatsplan',$team,'Teamplan lesen','plan.team.read','Lesen','team:'.$team,PermissionCondition::group($team));
            $rules[]=$this->rule('Schichtzuweisung',$team,'Eigene Wünsche im Team','plan.assignment.manage-own','Eigene Zuweisung','team:'.$team,PermissionCondition::all([PermissionCondition::group($team),PermissionCondition::self()]));
            $rules[]=$this->rule('Schichtpräferenz',$team,'Eigene Reaktion und Anmerkung','plan.assignment.preference.manage-own','Eigene Präferenz','team:'.$team,PermissionCondition::all([PermissionCondition::group($team),PermissionCondition::self()]));
            $rules[]=$this->rule('Schichtgrenzen',$team,'Eigene Wochen- und Monatsgrenzen','plan.workload.manage-own','Eigene Grenzen','team:'.$team,PermissionCondition::all([PermissionCondition::group($team),PermissionCondition::self()]));
            $rules[]=$this->rule('Feste Schichten',$team,'Eigene regelmäßige Schichten und Konflikteskalation','plan.fixed-shift.manage-own','Eigene feste Schichten','team:'.$team,PermissionCondition::all([PermissionCondition::group($team),PermissionCondition::self()]));
            $condition=PermissionCondition::all([PermissionCondition::group($team),PermissionCondition::group($eb)]);
            $rules[]=$this->rule('Monatsplan',$team,'Fremdzuweisungen und Teamkonfiguration','plan.team.coordinate','Koordinieren','team:'.$team,$condition);
            $rules[]=$this->rule('Monatsplan',$team,'Status planned/approved ändern','plan.status.transition','Status ändern','team:'.$team,$condition);
            $rules[]=$this->rule('Auslastungsübersicht',$team,'Grenzen und Zählstände des Teams lesen','plan.workload.read-team','Teamübersicht','team:'.$team,$condition);
            $rules[]=$this->rule('Festschichtkonflikt',$team,'Eskalierte Konflikte lesen und auflösen','plan.fixed-conflict.resolve','Konflikte lösen','team:'.$team,$condition);
        }
        $rules[]=$this->rule('Administration','Demo-Pack','Nextcloud-Administration mit aktiver app-lokaler Freigabe','plan.demo.manage','Demo verwalten','app',PermissionCondition::all([PermissionCondition::nextcloudAdmin(),PermissionCondition::temporaryAppAdminGrant()]));
        return new PermissionProviderResult($rules);
    }
    private function rule(string $type,string $name,string $detail,string $key,string $label,string $scope,PermissionCondition $condition):PermissionRule{return new PermissionRule($type,$name,$detail,$key,$label,'allow',$scope,$condition,'flzplaner:TeamAccessService','high');}
}
