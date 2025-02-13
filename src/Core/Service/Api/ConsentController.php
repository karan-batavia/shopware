<?php declare(strict_types=1);

namespace Shopware\Core\Service\Api;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\App\Privileges\Utils;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\ServiceException;
use Shopware\Core\Service\ServicePrivileges;
use Shopware\Core\Service\ServiceRegistryClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('framework')]
#[Route(defaults: ['_routeScope' => ['api']])]
class ConsentController
{
    public function __construct(
        private readonly ServicePrivileges $servicePrivileges,
        private readonly ServiceRegistryClient $serviceRegistryClient
    ) {
    }

    #[Route(path: '/api/services/consent', name: 'api.service.get_consent', methods: [Request::METHOD_GET])]
    public function getConsent(Context $context): JsonResponse
    {
        $this->getUserIdFromContext($context);

        return new JsonResponse([
            'status' => $this->servicePrivileges->getConsentStatus(),
            'requestedPermissions' => Utils::makeCategorizedPermissions($this->servicePrivileges->getPendingPrivileges()),
            'dataAgreementUrl' => $this->serviceRegistryClient->getDataAgreementUrl(),
        ]);
    }

    #[Route(path: '/api/services/accept-consent', name: 'api.service.accept_consent', methods: [Request::METHOD_POST])]
    public function acceptConsent(Context $context): Response
    {
        $this->getUserIdFromContext($context);

        try {
            $this->servicePrivileges->accept($context);
        } catch (\Throwable) {
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/services/revoke-consent', name: 'api.service.revoke_consent', methods: [Request::METHOD_POST])]
    public function revokeConsent(Context $context): Response
    {
        $this->getUserIdFromContext($context);

        try {
            $this->servicePrivileges->revoke($context);
        } catch (\Throwable) {
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function getUserIdFromContext(Context $context): string
    {
        $source = $context->getSource();

        if (!$source instanceof AdminApiSource) {
            throw ServiceException::invalidContextSource(AdminApiSource::class, $source::class);
        }

        if ($source->getUserId() === null) {
            throw ServiceException::missingUserInContextSource($source::class);
        }

        return $source->getUserId();
    }
}
