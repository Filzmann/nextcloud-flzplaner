<?php
declare(strict_types=1);
namespace OCA\FlzPlaner\Permission;
interface PlanerPermissionSourceInterface { public function teamGroupIds(): array; public function ebGroupId(): string; }
