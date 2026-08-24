<?php
declare(strict_types=1);
namespace OCA\AdPlaner\Permission;
use OCA\FilzmannPermissionMatrix\PublicApi\V1\RegisterPermissionProvidersEvent;
final class PlanerPermissionProviderListener { public function __construct(private PlanerPermissionProvider $provider){} public function handle(object $event):void{if($event instanceof RegisterPermissionProvidersEvent)$event->register($this->provider);} }
