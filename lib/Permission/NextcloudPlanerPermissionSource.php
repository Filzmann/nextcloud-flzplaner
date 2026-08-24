<?php
declare(strict_types=1);
namespace OCA\AdPlaner\Permission;
use OCA\LocalBase\Organization\AdOrganizationSettingsService;
use OCP\IGroupManager;
final class NextcloudPlanerPermissionSource implements PlanerPermissionSourceInterface {
    public function __construct(private IGroupManager $groups, private AdOrganizationSettingsService $organization) {}
    public function teamGroupIds(): array {
        $prefix=$this->organization->definition()->teamGroupPrefix();$ids=[];
        foreach($this->groups->search($prefix,10000,0) as $group){$id=(string)$group->getGID();if($id!==$prefix&&str_starts_with($id,$prefix))$ids[]=$id;}
        $ids=array_values(array_unique($ids));sort($ids,SORT_NATURAL|SORT_FLAG_CASE);return $ids;
    }
    public function ebGroupId(): string { return (string)$this->organization->definition()->roleGroupId('eb'); }
}
