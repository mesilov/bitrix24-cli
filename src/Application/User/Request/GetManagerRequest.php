<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\User\Request;

final readonly class GetManagerRequest
{
    public function __construct(public string $source, public ?int $departmentId, public int $configuredId = 0)
    {
    }
}
