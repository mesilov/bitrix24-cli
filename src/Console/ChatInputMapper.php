<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console;

use Bitrix24\CLI\Application\Chat\ChatTarget;
use Bitrix24\CLI\Application\Chat\ChatTargetParser;
use Bitrix24\CLI\Application\Chat\MessagePeriodResolver;
use Bitrix24\CLI\Application\Chat\Request\FindChatsRequest;
use Bitrix24\CLI\Application\Chat\Request\ListChatMessagesRequest;
use Bitrix24\CLI\Application\Chat\Request\SendChatMessageRequest;
use Bitrix24\CLI\Application\Chat\Request\ShowChatCallRequest;
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
        return new ListChatMessagesRequest($target, $period, $limit, $this->numbers->id($input->getOption('max-messages') ?? 10000, '--max-messages'), !$input->getOption('no-call-details'));
    }

    public function listCalls(InputInterface $input): ListChatMessagesRequest
    {
        $target = $this->target($input);
        $period = $this->periods->resolve($input->getOption('period'), $input->getOption('from'), $input->getOption('to'), $input->getOption('timezone'));
        return new ListChatMessagesRequest($target, $period, null, $this->numbers->id($input->getOption('max-messages') ?? 10000, '--max-messages'));
    }

    public function showCall(InputInterface $input): ShowChatCallRequest
    {
        return new ShowChatCallRequest($this->target($input), $this->numbers->id($input->getArgument('CALL_ID'), 'CALL_ID'), $this->numbers->id($input->getOption('max-messages') ?? 10000, '--max-messages'));
    }

    public function find(InputInterface $input): FindChatsRequest
    {
        $name = $input->getOption('name');
        $name = is_string($name) ? trim($name) : '';
        if (mb_strlen($name) < 2) {
            throw Failure::usage('Pass --name with at least 2 characters.');
        }

        if ($input->getOption('all') && $input->getOption('limit') !== null) {
            throw Failure::usage('Choose --limit or --all.');
        }

        $limit = $input->getOption('all') ? null : $this->numbers->id($input->getOption('limit') ?? 50, '--limit');
        return new FindChatsRequest($name, $this->numbers->id($input->getOption('max-scan') ?? 10000, '--max-scan'), $limit);
    }

    public function send(InputInterface $input): SendChatMessageRequest
    {
        $hasTarget = $input->getArgument('TARGET') !== null;
        $name = $input->getOption('name');
        if ($hasTarget === ($name !== null)) {
            throw Failure::usage('Pass TARGET or --name, not both.');
        }

        if ($hasTarget && $input->getOption('max-scan') !== null) {
            throw Failure::usage('--max-scan applies only to --name.');
        }

        $target = $hasTarget ? $this->target($input) : null;
        $name = is_string($name) ? trim($name) : null;
        if (!$hasTarget && ($name === null || mb_strlen($name) < 2)) {
            throw Failure::usage('Pass --name with at least 2 characters.');
        }

        $text = (string) $this->numbers->text($input, 'text');
        return new SendChatMessageRequest($target, $hasTarget ? null : $name, $text, $this->numbers->id($input->getOption('max-scan') ?? 10000, '--max-scan'));
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
