<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\User\Request\FindUsersRequest;
use Bitrix24\CLI\Application\User\Request\GetManagerRequest;
use Bitrix24\CLI\Application\User\Request\ListColleaguesRequest;
use Bitrix24\CLI\Application\User\UserQuery;
use Bitrix24\CLI\Infrastructure\Connection\ConnectionManager;
use Symfony\Component\Console\Input\InputInterface;

final readonly class UserInputMapper
{
    public function __construct(private TaskInputMapper $numbers, private ConnectionManager $connections)
    {
    }

    private function query(InputInterface $input): UserQuery
    {
        if ($input->getOption('all') && $input->getOption('limit') !== null) {
            throw Failure::usage('Choose --limit or --all.');
        }

        return new UserQuery($input->getOption('all') ? null : $this->numbers->id($input->getOption('limit') ?? 50, '--limit'), $this->numbers->id($input->getOption('max-scan') ?? 1000, '--max-scan'));
    }

    private function department(InputInterface $input): ?int
    {
        return $input->getOption('department-id') === null ? null : $this->numbers->id($input->getOption('department-id'), '--department-id');
    }

    public function find(InputInterface $input): FindUsersRequest
    {
        $name = $input->getOption('name');
        $email = $input->getOption('email');
        if (($name === null) === ($email === null)) {
            throw Failure::usage('Choose exactly one --name TEXT or --email EMAIL.');
        }

        $value = $name ?? $email;
        if (!is_string($value) || preg_match('//u', $value) !== 1 || preg_match('/\S/u', $value) !== 1) {
            throw Failure::usage('Provide a nonempty UTF-8 employee name or email.');
        }

        $value = trim($value);
        if ($email !== null && (str_contains($value, '*') || str_contains($value, '%') || filter_var($value, FILTER_VALIDATE_EMAIL) === false)) {
            throw Failure::usage('Provide an exact valid --email address without wildcards.');
        }

        return new FindUsersRequest($this->query($input), $name === null ? null : $value, $email === null ? null : $value);
    }

    public function colleagues(InputInterface $input): ListColleaguesRequest
    {
        return new ListColleaguesRequest($this->query($input), $this->department($input));
    }

    public function manager(InputInterface $input): GetManagerRequest
    {
        $source = $input->getOption('source');
        if (!in_array($source, ['org', 'config'], true) || ($source === 'config' && $input->getOption('department-id') !== null)) {
            throw Failure::usage('Use --source org or config; --department-id applies only to org.');
        }

        $id = $source === 'config' ? ($this->connections->userDefaults()['managerId'] ?? null) : 0;
        if ($id === null) {
            throw new Failure('configuration-error', 'Select a saved profile with default.users.managerId for --source config.');
        }

        return new GetManagerRequest($source, $this->department($input), $id);
    }
}
