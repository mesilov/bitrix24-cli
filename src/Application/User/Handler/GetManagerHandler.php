<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\User\Handler;

use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\User\UserDirectoryService;
use Bitrix24\CLI\Application\User\Request\GetManagerRequest;

final readonly class GetManagerHandler
{
    public function __construct(private UserDirectoryService $directory)
    {
    }

    public function routes(GetManagerRequest $request): array
    {
        return $request->source === 'config' ? [new Route('user.get', 1)] : [new Route('user.current', 1), new Route('department.get', 1), new Route('user.get', 1)];
    }

    public function prepare(GetManagerRequest $request): PreparedOperation
    {
        return new PreparedOperation([], fn (): OperationResult => $this->directory->manager($request));
    }
}
