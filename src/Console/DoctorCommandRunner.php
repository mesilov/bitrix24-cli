<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console;

use Bitrix24\CLI\Application\Doctor\FeedbackGateway;
use Bitrix24\CLI\Application\Doctor\ProblemTrajectoryInput;
use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Infrastructure\Connection\ConfigDirectoryResolver;
use Bitrix24\CLI\Infrastructure\Doctor\MockFeedbackGateway;
use Bitrix24\CLI\Infrastructure\Doctor\ProblemTrajectoryStore;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class DoctorCommandRunner
{
    public function __construct(private ProblemTrajectoryStore $store, private ConfigDirectoryResolver $directories, private InputSourceReader $reader, private CommandRunner $runner, private ResultPresenter $presenter, private FeedbackGateway $feedbackGateway, private MockFeedbackGateway $mockFeedbackGateway)
    {
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        $action = $input->getOption('action');
        $files = ['record' => 'report-file', 'configure' => 'settings-file', 'record-analysis' => 'batch-file', 'submit' => 'batch-file', 'reconcile' => 'batch-file'];
        if (!in_array($action, ['record', 'status', 'list', 'configure', 'record-offer', 'record-analysis', 'submit', 'reconcile', 'recover'], true)) {
            throw Failure::usage('Unknown doctor action; consult doctor:problem-trajectory help.');
        }

        $source = $files[$action] ?? null;
        foreach (['report-file', 'settings-file', 'batch-file'] as $option) {
            if ($input->getOption($option) !== null && $option !== $source) {
                throw Failure::usage('Input file option does not belong to the selected action.');
            }
        }

        if ($source !== null && $input->getOption($source) === null) {
            throw Failure::usage('Selected doctor action requires its documented input file option.');
        }

        if ($source !== null && $input->getOption($source) === '-' && $input->getOption('config') === '-') {
            throw Failure::usage('Doctor payload and runtime config cannot both consume stdin.');
        }

        if (($input->getOption('mock-feedback') && !in_array($action, ['submit', 'reconcile'], true)) || ($input->getOption('confirm-publication') && $action !== 'submit') || ($input->getOption('idempotency-key') !== null && $action !== 'record') || ($action !== 'list' && $input->hasParameterOption(['--state', '--limit', '--offset']))) {
            throw Failure::usage('Selected doctor options do not belong to this action.');
        }

        $this->reader->reset($input);
        $context = $this->runner->context($input);
        $directory = $this->directories->resolve($input->getOption('config-dir'));
        $payload = $source === null ? [] : $this->payload($input->getOption($source), $action === 'record' ? ProblemTrajectoryInput::MAX_REPORT_BYTES : ProblemTrajectoryInput::MAX_BATCH_BYTES);
        $data = match ($action) {
            'record' => $this->store->record($directory, $payload, $input->getOption('idempotency-key')),
            'status' => $this->store->status($directory),
            'list' => $this->store->listing($directory, $input->getOption('state'), $this->integer($input->getOption('limit')), $this->integer($input->getOption('offset'))),
            'configure' => $this->store->configure($directory, $payload),
            'record-offer' => $this->store->offered($directory),
            'record-analysis' => $this->store->analysis($directory, $payload),
            'recover' => $this->store->recover($directory),
            default => $this->handoff($directory, $payload, $action, $input->getOption('mock-feedback'), $input->getOption('confirm-publication')),
        };
        $status = ($data['complete'] ?? true) ? 0 : 3;
        $meta = ['local' => !in_array($action, ['submit', 'reconcile'], true) || ($data['mocked'] ?? true), 'complete' => $data['complete'] ?? true];
        if ($status === 3) {
            $meta['reason'] = 'Doctor action has unresolved groups or damaged/remaining cards; confirmed local results are retained.';
        }

        if (isset($data['outcomeUnknown'])) {
            $meta['outcomeUnknown'] = $data['outcomeUnknown'];
        }

        $operationResult = new OperationResult($action === 'list' ? 'doctor-cards' : 'doctor', $data, $meta, $status);
        $this->presenter->success($operationResult, $context->outputMode, $output, 'doctor:problem-trajectory', []);
        return $status;
    }

    private function payload(string $path, int $maximum): array
    {
        $raw = $this->reader->read($path, $maximum);
        $payload = $this->reader->object($raw);
        try {
            $object = json_decode($raw, false, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw Failure::usage('Doctor payload exceeds the supported JSON nesting depth.');
        }

        foreach (['availableCommands', 'wrapperAlgorithm', 'groups', 'groupRefs'] as $field) {
            if (property_exists($object, $field) && !is_array($object->{$field})) {
                throw Failure::usage('Doctor list fields must be JSON arrays.');
            }
        }

        foreach ($object->groups ?? [] as $group) {
            if (!$group instanceof \stdClass) {
                throw Failure::usage('Analysis groups must be JSON objects.');
            }

            foreach (['cardIds', 'syntheticReproduction', 'acceptanceCriteria', 'issueLinks'] as $field) {
                if (property_exists($group, $field) && !is_array($group->{$field})) {
                    throw Failure::usage('Analysis list fields must be JSON arrays.');
                }
            }
        }

        return $payload;
    }

    private function integer(mixed $value): int
    {
        if (!is_string($value) || preg_match('/^(?:0|[1-9][0-9]*)$/D', $value) !== 1 || strlen($value) > strlen((string) PHP_INT_MAX) || (strlen($value) === strlen((string) PHP_INT_MAX) && strcmp($value, (string) PHP_INT_MAX) > 0)) {
            throw Failure::usage('Doctor pagination needs nonnegative integer values.');
        }

        return (int) $value;
    }

    private function handoff(string $directory, array $selection, string $action, bool $mocked, bool $confirmed): array
    {
        $refs = ProblemTrajectoryInput::selection($selection);
        if ($action === 'submit' && !$confirmed) {
            throw Failure::usage('Review the saved publication text and provide --confirm-publication for this selected batch.');
        }

        $gateway = $mocked ? $this->mockFeedbackGateway : $this->feedbackGateway;
        if (!$gateway->available()) {
            throw new Failure('endpoint-unavailable', 'Feedback endpoint is not available; local cards and analysis are retained. --mock-feedback is an offline rehearsal only.');
        }

        // Validate every selected snapshot before reserving or contacting the gateway.
        foreach ($refs as $ref) {
            $group = $this->store->group($directory, $ref);
            if ($group['analysis']['decision'] !== 'candidate' || $group['analysis']['deduplication'] !== 'checked') {
                throw Failure::usage('Handoff requires candidate groups with checked issue deduplication.');
            }

            if ($action === 'reconcile' && (!isset($group['feedback']) || $group['feedback']['mocked'] !== $gateway->mocked())) {
                throw Failure::usage('Reconcile requires a previous attempt in the same feedback mode.');
            }
        }

        $results = [];
        $complete = true;
        $unknown = false;
        foreach ($refs as $ref) {
            $reserved = false;
            try {
                $group = $action === 'submit' ? $this->store->reserve($directory, $ref, $gateway->mocked()) : $this->store->group($directory, $ref);
                if (in_array($group['state'], ['submitted', 'mock-submitted'], true)) {
                    $results[] = $this->store->publicGroup($group);
                    continue;
                }

                $reserved = true;
                $receipt = $action === 'submit' ? $gateway->submit($group['publication'], $group['feedback']['idempotencyKey']) : $gateway->reconcile($group['feedback']['idempotencyKey']);
                if (!$receipt instanceof \Bitrix24\CLI\Application\Doctor\FeedbackReceipt) {
                    $results[] = $this->store->failed($directory, $ref, true);
                    $complete = false;
                    $unknown = true;
                } else {
                    $results[] = $this->store->receipt($directory, $ref, $receipt);
                }
            } catch (\Throwable $error) {
                $isUnknown = !$error instanceof Failure || $error->outcomeUnknown;
                $result = $reserved ? $this->store->failed($directory, $ref, $isUnknown) : ['groupRef' => $ref];
                $safeCodes = ['usage-error', 'configuration-error', 'storage-error', 'storage-recovery-required', 'submission-unknown', 'feedback-response-invalid', 'feedback-error'];
                $result['errorCode'] = $error instanceof Failure && in_array($error->errorCode, $safeCodes, true) ? $error->errorCode : 'feedback-error';
                $results[] = $result;
                $complete = false;
                $unknown = $unknown || $isUnknown;
            }
        }

        return ['groups' => $results, 'complete' => $complete, 'outcomeUnknown' => $unknown, 'mocked' => $gateway->mocked(), 'delivered' => $complete && !$gateway->mocked()];
    }
}
