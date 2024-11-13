<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Api;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Privileges\Privileges;
use Shopware\Core\Framework\App\Privileges\Utils;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('core')]
class AppPermissionController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Privileges $privileges
    ) {
    }

    #[Route(path: '/api/app-system/permissions/requested', name: 'api.app_system.permissions.requested', methods: [Request::METHOD_GET])]
    public function getRequestedPermissions(Context $context): JsonResponse
    {
        $userId = $this->getUserIdFromContext($context);

        return new JsonResponse([
            'requestedPermissions' => array_map(
                fn (array $privileges) => Utils::makeCategorizedPermissions($privileges),
                $this->privileges->getPendingPrivilegesForAllApps()
            ),
        ]);
    }

    #[Route(path: '/api/app-system/{appName}/permissions/accept', name: 'api.app_system.permissions.accept', methods: [Request::METHOD_POST])]
    public function acceptPermissions(Request $request, Context $context, string $appName): Response
    {
        $userId = $this->getUserIdFromContext($context);

        $permissionsToAccept = $request->toArray();
        $permissionsToAccept = array_filter($permissionsToAccept, is_string(...));

        if (\count($permissionsToAccept) === 0 || \count($request->toArray()) !== \count($permissionsToAccept)) {
            throw AppException::invalidPermissions();
        }

        $id = $this->fetchAppId($appName);

        try {
            $this->privileges->acceptOnly($id, $permissionsToAccept, $context);
        } catch (\Throwable) {
            // no-op
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function fetchAppId(string $appName): string
    {
        $id = $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM app WHERE name = ?', [$appName]);

        if (!$id) {
            throw AppException::notFoundByField($appName, 'name');
        }

        return $id;
    }

    private function getUserIdFromContext(Context $context): string
    {
        $source = $context->getSource();

        if (!$source instanceof AdminApiSource) {
            throw AppException::invalidContextSource(AdminApiSource::class, $source::class);
        }

        if ($source->getUserId() === null) {
            throw AppException::missingUserInContextSource($source::class);
        }

        return $source->getUserId();
    }
}
