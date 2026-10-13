<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\User\Handler;

use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\User\UserDirectoryService;
use Bitrix24\CLI\Application\User\Request\FindUsersRequest;

final readonly class FindUsersHandler
{
    public function __construct(private UserDirectoryService $directory)
    {
    }

    public function routes(): array
    {
        return [new Route('user.get', 1)];
    }

    public function prepare(FindUsersRequest $request): PreparedOperation
    {
        return new PreparedOperation([], fn (): OperationResult => $this->directory->find($request));
    }
}
