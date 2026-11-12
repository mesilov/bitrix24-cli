<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console;

use Bitrix24\CLI\Application\Chat\ChatTarget;
use Bitrix24\CLI\Application\Chat\ChatTargetParser;
use Bitrix24\CLI\Application\Chat\MessagePeriodResolver;
use Bitrix24\CLI\Application\Chat\Request\ListChatMessagesRequest;
use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Infrastructure\Connection\ConnectionResolver;
use Bitrix24\CLI\Infrastructure\Connection\FileCredentialStore;
use Symfony\Component\Console\Input\InputInterface;

final readonly class ChatInputMapper
{
    public function __construct(private ConnectionResolver $connection, private MessagePeriodResolver $periods, private TaskInputMapper $numbers)
    {
    }

    public function listMessages(InputInterface $input): ListChatMessagesRequest
    {
        $target = $this->target($input);
        $period = $this->periods->resolve($input->getOption('period'), $input->getOption('from'), $input->getOption('to'), $input->getOption('timezone'));
        $limit = $input->getOption('limit') === null ? null : $this->numbers->id($input->getOption('limit'), '--limit');
        return new ListChatMessagesRequest($target, $period, $limit, $this->numbers->id($input->getOption('max-messages') ?? 10000, '--max-messages'));
    }

    /** Parses TARGET locally; credentials are not read and the URL is never opened. */
    public function target(InputInterface $input): ChatTarget
    {
        return (new ChatTargetParser())->parse($input->getArgument('TARGET'));
    }

    /** Rejects a URL of another portal before any API request. */
    public function assertPortal(ChatTarget $target): void
    {
        if ($target->host === null) {
            return;
        }

        $selected = FileCredentialStore::hostname($this->connection->webhook());
        if ($target->host !== $selected) {
            throw new Failure('foreign-portal', 'The TARGET URL belongs to another portal; select the matching connection profile.', 2, ['urlHost' => $target->host, 'connectionHost' => $selected]);
        }
    }
}
