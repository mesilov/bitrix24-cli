<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Chat;

use Bitrix24\CLI\Application\Chat\Request\ListChatMessagesRequest;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;

final readonly class ChatMessagesService
{
    public function __construct(private ChatDestinationService $destinations, private ChatHistoryScanner $scanner)
    {
    }

    /** @return list<Route> */
    public function routes(ListChatMessagesRequest $request): array
    {
        return [...$this->destinations->routes($request->target), new Route('im.dialog.messages.get', 1)];
    }

    public function prepare(ListChatMessagesRequest $request): PreparedOperation
    {
        return new PreparedOperation([], fn (): OperationResult => $this->scanner->scan($this->destinations->resolve($request->target), $request->period, $request->limit, $request->maxMessages), $request->target->target());
    }
}
