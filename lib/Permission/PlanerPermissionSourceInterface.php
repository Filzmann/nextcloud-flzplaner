<?php
declare(strict_types=1);
namespace OCA\AdPlaner\Permission;
interface PlanerPermissionSourceInterface { public function teamGroupIds(): array; public function ebGroupId(): string; }
