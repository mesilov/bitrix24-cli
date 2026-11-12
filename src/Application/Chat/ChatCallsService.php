<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Chat;

use Bitrix24\CLI\Application\Chat\Port\CallGateway;
use Bitrix24\CLI\Application\Chat\Request\ListChatMessagesRequest;
use Bitrix24\CLI\Application\Chat\Request\ShowChatCallRequest;
use Bitrix24\CLI\Application\Clock;
use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;

/**
 * chats:calls:list (PM-10) and chats:calls:show (CHAT-03/CHAT-05) over the verified chat history walk.
 */
final readonly class ChatCallsService
{
    /** Messages about the end of a call may lag behind END_DATE. */
    private const SHOW_TAIL_SECONDS = 300;

    public function __construct(private ChatDestinationService $destinations, private ChatHistoryScanner $scanner, private CallEnricher $enricher, private CallGateway $calls, private Clock $clock)
    {
    }

    /** @return list<Route> */
    public function routes(ChatTarget $target): array
    {
        return [...$this->destinations->routes($target), new Route('im.dialog.messages.get', 1), new Route('im.call.get', 1), new Route('im.user.list.get', 1), new Route('call.followup.get', 3)];
    }

    public function list(ListChatMessagesRequest $request): PreparedOperation
    {
        return new PreparedOperation([], function () use ($request): OperationResult {
            $scan = $this->scanner->scan($this->destinations->resolve($request->target), $request->period, null, $request->maxMessages);
            $enriched = $scan->exitStatus === 130 ? ['calls' => [], 'meta' => ['found' => 0, 'enriched' => 0, 'unavailable' => 0, 'confirmed' => 0]] : $this->enricher->enrich((int) $scan->data['destination']['chatId'], $scan->data['items']);
            $items = $enriched['calls'];
            usort($items, static fn (array $a, array $b): int => [ParticipantInference::time($a['startedAt']) ?? PHP_INT_MAX, $a['callId']] <=> [ParticipantInference::time($b['startedAt']) ?? PHP_INT_MAX, $b['callId']]);
            return new OperationResult('chat-calls', [
                'destination' => $scan->data['destination'],
                'period' => $scan->data['period'],
                'items' => $items,
            ], [...$this->scanMeta($scan->meta), 'calls' => $enriched['meta']], $scan->exitStatus);
        }, $request->target->target());
    }

    public function show(ShowChatCallRequest $request): PreparedOperation
    {
        return new PreparedOperation([], function () use ($request): OperationResult {
            $resolved = $this->destinations->resolve($request->target);
            $chatId = (int) $resolved['destination']['chatId'];
            $details = $this->calls->details($request->callId);
            if ($details['status'] !== 'ok') {
                throw new Failure('call-unavailable', 'Call details are unavailable (' . $details['reason'] . '); find the call in chats:messages:list instead.', 1, ['callId' => $request->callId, 'reason' => $details['reason']]);
            }

            if (CallMessage::positive($details['call']['CHAT_ID'] ?? null) !== $chatId) {
                throw new Failure('resource-binding-unverified', 'The call does not belong to the selected chat.', 4, ['callId' => $request->callId, 'chatId' => $chatId]);
            }

            $now = $this->clock->now();
            $start = $this->date($details['call']['START_DATE'] ?? null);
            if (!$start instanceof \DateTimeImmutable) {
                throw new Failure('call-unavailable', 'The call has no start date; find it in chats:messages:list instead.', 1, ['callId' => $request->callId, 'reason' => 'call-start-unknown']);
            }

            $end = $this->date($details['call']['END_DATE'] ?? null) ?? $now;
            $messagePeriod = new MessagePeriod('call', $start, $end->modify('+' . self::SHOW_TAIL_SECONDS . ' seconds'), $now, null);
            $scan = $this->scanner->scan($resolved, $messagePeriod, null, $request->maxMessages);
            $enriched = $this->enricher->enrich($chatId, $scan->data['items'], [$request->callId => $details], [$request->callId]);
            $record = array_values(array_filter($enriched['calls'], static fn (array $call): bool => $call['callId'] === $request->callId))[0];
            return new OperationResult('chat-call', ['destination' => $resolved['destination'], 'period' => $messagePeriod->toArray(), 'call' => $record], [...$this->scanMeta($scan->meta), 'calls' => $enriched['meta']], $scan->exitStatus);
        }, $request->target->target());
    }

    private function scanMeta(array $meta): array
    {
        return array_intersect_key($meta, array_flip(['scope', 'complete', 'reason', 'scanned', 'matched', 'pages', 'oldestScannedDate', 'periodStartPassed']));
    }

    private function date(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
