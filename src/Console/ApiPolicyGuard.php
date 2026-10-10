<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Bootstrap\CommandCatalog;

final class ApiPolicyGuard
{
    public function validate(string $command, array $routes, string $policy): void
    {
        foreach ($routes as $route) {
            if (!$route instanceof Route || !in_array([$route->method, $route->version], CommandCatalog::ROUTES[$command] ?? [], true)) {
                throw new Failure('configuration-error', 'The command declared an unsupported API route.');
            }

            if ($policy === 'strict-rest3' && $route->version !== 3) {
                throw new Failure('policy-denied', 'This command requires an IM or REST 1.0 route; strict-rest3 prohibits it.', 4);
            }
        }
    }
}
