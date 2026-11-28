<?php

declare(strict_types=1);

namespace OCA\FlzPlaner\Service;

use OCA\FlzOrganization\PublicApi\V1\Contract;
use OCA\FlzOrganization\PublicApi\V1\OrganizationSnapshotService;
use OCA\FlzPlaner\Model\PlanerOrganizationDefinition;
use OCP\App\IAppManager;
use OCP\Server;
use Psr\Log\LoggerInterface;

/** Fail-closed consumer of the public flz_organization V1 organization contract. */
final class PlanerOrganizationGateway
{
    private const PROVIDER_APP_ID = 'flz_organization';
    private const SUPPORTED_CONTRACT_VERSION = '1.0';

    public function __construct(
        private IAppManager $apps,
        private LoggerInterface $logger,
    )
    {
    }

    public function definition(): ?PlanerOrganizationDefinition
    {
        try
        {
            if (!in_array(self::PROVIDER_APP_ID, $this->apps->getEnabledApps(), true))
            {
                return null;
            }
            if (!class_exists(Contract::class) || !Contract::supports(self::SUPPORTED_CONTRACT_VERSION))
            {
                return null;
            }
            $service = Server::get(OrganizationSnapshotService::class);
            if (!$service instanceof OrganizationSnapshotService)
            {
                return null;
            }
            $snapshot = $service->snapshot();
            if ($snapshot->contractVersion() !== self::SUPPORTED_CONTRACT_VERSION || !$snapshot->isValid())
            {
                return null;
            }
            return PlanerOrganizationDefinition::fromArray($snapshot->definition());
        }
        catch (\Throwable $error)
        {
            $this->logger->warning(
                'Assistenzplanung organization snapshot unavailable',
                [
                    'app' => 'flzplaner',
                    'provider' => self::PROVIDER_APP_ID,
                    'exceptionClass' => $error::class,
                ],
            );
            return null;
        }
    }

    public function definitionOrDefaults(): PlanerOrganizationDefinition
    {
        return $this->definition() ?? PlanerOrganizationDefinition::defaults();
    }
}
