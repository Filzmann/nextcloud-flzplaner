<?php
declare(strict_types=1);
namespace OCA\FlzPlaner\Permission;
use OCA\FlzPermissionMatrix\PublicApi\V1\{PermissionCondition,PermissionProvider,PermissionProviderDescriptor,PermissionProviderResult,PermissionRule};
use OCP\IL10N;
final class PlanerPermissionProvider implements PermissionProvider {
    public function __construct(private PlanerPermissionSourceInterface $source, private ?IL10N $l10n = null) {}

    /** Lokalisierung fachlicher Texte; im Test ohne IL10N identity. */
    private function t(string $text): string
    {
        return $this->l10n?->t($text) ?? $text;
    }
    public function descriptor():PermissionProviderDescriptor{return new PermissionProviderDescriptor('flzplaner',$this->t('Filzmann Assistenzplanung'),'1.0',['permissions']);}
    public function collect():PermissionProviderResult{
        $rules=[];$eb=$this->source->ebGroupId();
        foreach($this->source->teamGroupIds() as $team){
            $rules[]=$this->rule($this->t('Monatsplan'),$team,$this->t('Teamplan lesen'),'plan.team.read',$this->t('Lesen'),'team:'.$team,PermissionCondition::group($team));
            $rules[]=$this->rule($this->t('Schichtzuweisung'),$team,$this->t('Eigene Wünsche im Team'),'plan.assignment.manage-own',$this->t('Eigene Zuweisung'),'team:'.$team,PermissionCondition::all([PermissionCondition::group($team),PermissionCondition::self()]));
            $rules[]=$this->rule($this->t('Schichtpräferenz'),$team,$this->t('Eigene Reaktion und Anmerkung'),'plan.assignment.preference.manage-own',$this->t('Eigene Präferenz'),'team:'.$team,PermissionCondition::all([PermissionCondition::group($team),PermissionCondition::self()]));
            $rules[]=$this->rule($this->t('Schichtgrenzen'),$team,$this->t('Eigene Wochen- und Monatsgrenzen'),'plan.workload.manage-own',$this->t('Eigene Grenzen'),'team:'.$team,PermissionCondition::all([PermissionCondition::group($team),PermissionCondition::self()]));
            $rules[]=$this->rule($this->t('Feste Schichten'),$team,$this->t('Eigene regelmäßige Schichten und Konflikteskalation'),'plan.fixed-shift.manage-own',$this->t('Eigene feste Schichten'),'team:'.$team,PermissionCondition::all([PermissionCondition::group($team),PermissionCondition::self()]));
            $condition=PermissionCondition::all([PermissionCondition::group($team),PermissionCondition::group($eb)]);
            $rules[]=$this->rule($this->t('Schichtzuweisung'),$team,$this->t('Fremde Zuweisungen im eigenen Assistenzteam bearbeiten'),'plan.assignment.manage-team',$this->t('Fremdzuweisungen bearbeiten'),'team:'.$team,$condition);
            $rules[]=$this->rule($this->t('Teamkonfiguration'),$team,$this->t('Schichten und Teameinstellungen des eigenen Assistenzteams bearbeiten'),'plan.team-settings.manage',$this->t('Team konfigurieren'),'team:'.$team,$condition);
            $rules[]=$this->rule($this->t('Monatsplan'),$team,$this->t('Status planned/approved ändern'),'plan.status.transition',$this->t('Status ändern'),'team:'.$team,$condition);
            $rules[]=$this->rule($this->t('Auslastungsübersicht'),$team,$this->t('Grenzen und Zählstände des Teams lesen'),'plan.workload.read-team',$this->t('Teamübersicht'),'team:'.$team,$condition);
            $rules[]=$this->rule($this->t('Festschichtkonflikt'),$team,$this->t('Eskalierte Konflikte lesen und auflösen'),'plan.fixed-conflict.resolve',$this->t('Konflikte lösen'),'team:'.$team,$condition);
        }
        $grantConditions=[PermissionCondition::group('Datenschutzbeauftragte')];
        foreach($this->source->teamGroupIds() as $team)$grantConditions[]=PermissionCondition::all([PermissionCondition::group($team),PermissionCondition::group($eb)]);
        $rules[]=$this->rule($this->t('Administration'),$this->t('Demo-Pack'),$this->t('Native Nextcloud-Administration; ausschließlich synthetische Demodaten'),'plan.demo.manage',$this->t('Demo verwalten'),'app',PermissionCondition::nextcloudAdmin());
        $rules[]=$this->rule($this->t('Administration'),$this->t('Temporärer fachlicher Admin-Vollzugriff'),$this->t('Datenschutzbeauftragte oder zuständige Einsatzbegleitung'),'plan.admin-grant.manage',$this->t('Adminfreigaben verwalten'),'app',PermissionCondition::any($grantConditions));
        return new PermissionProviderResult($rules);
    }
    private function rule(string $type,string $name,string $detail,string $key,string $label,string $scope,PermissionCondition $condition):PermissionRule{return new PermissionRule($type,$name,$detail,$key,$label,'allow',$scope,$condition,'flzplaner:TeamAccessService','high');}
}
