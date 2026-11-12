<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Chat;

use Bitrix24\CLI\Application\Chat\Request\ListChatMessagesRequest;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;

final readonly class ChatMessagesService
{
    public function __construct(private ChatDestinationService $destinations, private ChatHistoryScanner $scanner, private CallEnricher $calls)
    {
    }

    /** @return list<Route> */
    public function routes(ListChatMessagesRequest $request): array
    {
        $routes = [...$this->destinations->routes($request->target), new Route('im.dialog.messages.get', 1)];
        if ($request->callDetails) {
            // Requested only when the period contains call messages; call.followup.get is optional (scope call).
            return [...$routes, new Route('im.call.get', 1), new Route('im.user.list.get', 1), new Route('call.followup.get', 3)];
        }

        return $routes;
    }

    public function prepare(ListChatMessagesRequest $request): PreparedOperation
    {
        return new PreparedOperation([], function () use ($request): OperationResult {
            $scan = $this->scanner->scan($this->destinations->resolve($request->target), $request->period, $request->limit, $request->maxMessages);
            $data = [...$scan->data, 'items' => CallMessage::mark($scan->data['items'])];
            $meta = $scan->meta;
            if ($request->callDetails && $scan->exitStatus !== 130) {
                $calls = $this->calls->enrich((int) $scan->data['destination']['chatId'], $scan->data['items']);
                $data['calls'] = $calls['calls'];
                $meta['calls'] = $calls['meta'];
            } else {
                $meta['calls'] = ['found' => count(array_unique(array_column(array_column($data['items'], 'callRef'), 'callId'))), 'details' => 'disabled'];
            }

            return new OperationResult($scan->profile, $data, $meta, $scan->exitStatus);
        }, $request->target->target());
    }
}
