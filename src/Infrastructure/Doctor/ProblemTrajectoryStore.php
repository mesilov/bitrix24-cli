<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Doctor;

use Bitrix24\CLI\Application\Clock;
use Bitrix24\CLI\Application\Doctor\FeedbackReceipt;
use Bitrix24\CLI\Application\Doctor\ProblemTrajectoryInput;
use Bitrix24\CLI\Application\Doctor\TelemetrySettings;
use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\BuildVersion;
use Bitrix24\CLI\Infrastructure\Connection\FileProtection;
use Bitrix24\CLI\Infrastructure\Connection\ProfileRepository;

final readonly class ProblemTrajectoryStore
{
    public function __construct(private ProfileRepository $profiles, private FileProtection $protection, private Clock $clock)
    {
    }

    public function status(string $directory): array
    {
        return $this->read($directory, function (array $config, array $scan): array {
            $settings = TelemetrySettings::effective($config['telemetry'] ?? []);
            $counts = array_fill_keys(ProblemTrajectoryInput::STATES, 0);
            foreach ($scan['cards'] as $card) {
                $counts[$card['state']]++;
            }

            $last = max($settings['lastProblemTrajectoryProcessingAt'] ?? '', $settings['lastProblemTrajectoryAnalysisOfferedAt'] ?? '');
            $due = $counts['pending'] > 0 && ($last === '' || $this->clock->now()->getTimestamp() - (new \DateTimeImmutable($last))->getTimestamp() >= 604800);
            return ['telemetry' => $settings, 'counts' => $counts, 'fileCount' => $scan['count'], 'corruptFileCount' => $scan['corruptCount'], 'analysisDue' => $due, 'complete' => $scan['corruptCount'] === 0];
        });
    }

    public function listing(string $directory, string $state = 'pending', int $limit = 100, int $offset = 0): array
    {
        if (!in_array($state, [...ProblemTrajectoryInput::STATES, 'all'], true) || $limit < 1 || $limit > 100 || $offset < 0) {
            throw Failure::usage('Use a supported --state, --limit 1..100 and nonnegative --offset.');
        }

        return $this->read($directory, function (array $config, array $scan) use ($directory, $state, $limit, $offset): array {
            $items = array_values(array_filter($scan['cards'], static fn (array $card): bool => $state === 'all' || $card['state'] === $state));
            $total = count($items);
            $items = array_slice($items, $offset, $limit);
            foreach ($items as &$item) {
                unset($item['registrationHash'], $item['payloadHash']);
                if (isset($item['analysisRef'])) {
                    $group = $this->optionalDocument($directory, 'analyses/' . $item['analysisRef'] . '.json');
                    $item['analysis'] = $group === null ? null : $this->publicGroup($group);
                }
            }

            unset($item);
            return ['items' => $items, 'total' => $total, 'returned' => count($items), 'nextOffset' => $offset + count($items) < $total ? $offset + count($items) : null, 'complete' => $offset + count($items) >= $total && $scan['corruptCount'] === 0, 'corruptFileCount' => $scan['corruptCount']];
        });
    }

    public function configure(string $directory, array $settings): array
    {
        ProblemTrajectoryInput::settings($settings);
        return $this->mutate($directory, static fn (array $config, array $scan): array => [
            'telemetry' => $settings,
            'result' => ['telemetry' => TelemetrySettings::effective([...($config['telemetry'] ?? []), ...$settings])],
        ]);
    }

    public function record(string $directory, array $report, ?string $idempotencyKey = null): array
    {
        ProblemTrajectoryInput::report($report);
        if ($idempotencyKey !== null) {
            ProblemTrajectoryInput::identifier($idempotencyKey);
        }

        $payloadHash = hash('sha256', json_encode($report, JSON_THROW_ON_ERROR));
        $registrationHash = $idempotencyKey === null ? null : hash('sha256', $idempotencyKey);
        return $this->mutate($directory, function (array $config, array $scan) use ($report, $payloadHash, $registrationHash): array {
            $settings = TelemetrySettings::effective($config['telemetry'] ?? []);
            if (!$settings['collectProblemTrajectories']) {
                throw new Failure('collection-disabled', 'Local problem trajectory collection is disabled; existing cards are retained.');
            }

            if ($registrationHash !== null) {
                foreach ($scan['cards'] as $card) {
                    if (($card['registrationHash'] ?? null) === $registrationHash) {
                        if ($card['payloadHash'] !== $payloadHash) {
                            throw Failure::usage('Registration idempotency key already belongs to another report.');
                        }

                        return ['result' => ['cardId' => $card['cardId'], 'state' => $card['state'], 'duplicate' => true]];
                    }
                }
            }

            $delete = [];
            $required = max(0, $scan['count'] - $settings['maxProblemTrajectoryFiles'] + 1);
            foreach ($scan['cards'] as $card) {
                if (count($delete) < $required && $this->terminal($card)) {
                    $delete[] = $card['cardId'] . '.json';
                }
            }

            if (count($delete) < $required) {
                throw new Failure('storage-full', 'Problem trajectory storage is full of unfinished or damaged cards; analyze or explicitly dismiss them before adding another report.');
            }

            $id = bin2hex(random_bytes(16));
            $card = [...$report, 'cardId' => $id, 'createdAt' => $this->now(), 'state' => 'pending', 'reportedCliVersion' => $report['cliVersion'], 'cliVersion' => BuildVersion::version(), 'payloadHash' => $payloadHash, 'registrationHash' => $registrationHash];
            return ['documents' => [$id . '.json' => $card], 'delete' => $delete, 'result' => ['cardId' => $id, 'state' => 'pending', 'duplicate' => false, 'cliVersion' => $card['cliVersion'], 'versionMismatch' => $card['reportedCliVersion'] !== $card['cliVersion']]];
        });
    }

    public function offered(string $directory): array
    {
        return $this->mutate($directory, function (array $config, array $scan): array {
            if (!array_any($scan['cards'], static fn (array $card): bool => $card['state'] === 'pending')) {
                throw Failure::usage('No pending cards to offer for analysis.');
            }

            $date = $this->now();
            return ['telemetry' => ['lastProblemTrajectoryAnalysisOfferedAt' => $date], 'result' => ['lastProblemTrajectoryAnalysisOfferedAt' => $date]];
        });
    }

    public function analysis(string $directory, array $batch): array
    {
        ProblemTrajectoryInput::batch($batch);
        return $this->mutate($directory, function (array $config, array $scan) use ($directory, $batch): array {
            $documents = [];
            $results = [];
            $changed = false;
            foreach ($batch['groups'] as $group) {
                $ref = hash('sha256', $batch['batchId'] . ':' . $group['groupId']);
                $path = 'analyses/' . $ref . '.json';
                $fingerprint = hash('sha256', json_encode($group, JSON_THROW_ON_ERROR));
                $existing = $this->optionalDocument($directory, $path);
                if ($existing !== null) {
                    if (($existing['fingerprint'] ?? null) !== $fingerprint) {
                        throw Failure::usage('A saved batch/group reference cannot be reused with different contents.');
                    }

                    $this->activeGroup($existing, $scan);
                    $results[] = $this->publicGroup($existing);
                    continue;
                }

                $selected = [];
                foreach ($group['cardIds'] as $id) {
                    $card = $scan['cards'][$id] ?? null;
                    if ($card === null || !in_array($card['state'], ['pending', 'analyzed', 'mock-submitted'], true)) {
                        throw Failure::usage('Analysis must reference existing unfinished snapshot cards.');
                    }

                    $selected[] = $card;
                }

                $state = match ($group['decision']) {
                    'dismissed' => 'dismissed',
                    'linked-existing' => 'linked-existing',
                    default => 'analyzed',
                };
                $saved = ['groupRef' => $ref, 'batchId' => $batch['batchId'], 'fingerprint' => $fingerprint, 'analysis' => $group, 'state' => $state, 'publication' => $this->publication($group, $selected), 'createdAt' => $this->now()];
                $documents[$path] = $saved;
                foreach ($selected as $card) {
                    $card['state'] = $state;
                    $card['analysisRef'] = $ref;
                    unset($card['feedback']);
                    $documents[$card['cardId'] . '.json'] = $card;
                }

                $results[] = $this->publicGroup($saved);
                $changed = true;
            }

            return ['documents' => $documents, 'telemetry' => $changed ? ['lastProblemTrajectoryProcessingAt' => $this->now()] : [], 'result' => ['groups' => $results, 'duplicate' => !$changed]];
        });
    }

    public function group(string $directory, string $ref): array
    {
        ProblemTrajectoryInput::selection(['groupRefs' => [$ref]]);
        return $this->read($directory, function (array $config, array $scan) use ($directory, $ref): array {
            $group = $this->optionalDocument($directory, 'analyses/' . $ref . '.json');
            if ($group === null || ($group['groupRef'] ?? null) !== $ref) {
                throw Failure::usage('Saved analysis group does not exist.');
            }

            $this->activeGroup($group, $scan);
            return $group;
        });
    }

    public function reserve(string $directory, string $ref, bool $mocked): array
    {
        return $this->mutate($directory, function (array $config, array $scan) use ($directory, $ref, $mocked): array {
            $group = $this->optionalDocument($directory, 'analyses/' . $ref . '.json');
            if ($group === null) {
                throw Failure::usage('Saved analysis group does not exist.');
            }

            $this->activeGroup($group, $scan);
            if ($group['analysis']['decision'] !== 'candidate' || $group['analysis']['deduplication'] !== 'checked') {
                throw Failure::usage('Submit requires a candidate with checked issue deduplication.');
            }

            if (in_array($group['state'], ['submitted', 'mock-submitted'], true)) {
                if (($group['feedback']['mocked'] ?? null) !== $mocked) {
                    throw Failure::usage('Saved receipt belongs to a different feedback mode.');
                }

                return ['result' => $group];
            }

            if ($group['state'] === 'submission-unknown') {
                throw new Failure('submission-unknown', 'Reconcile the previous attempt before submitting again.', 1, [], true);
            }

            $group['state'] = 'submission-unknown';
            $group['feedback'] = ['idempotencyKey' => hash('sha256', 'problem-trajectory:' . $ref . ':' . ($mocked ? 'mock' : 'real')), 'mocked' => $mocked, 'delivered' => false, 'receiptId' => null];
            return ['documents' => $this->groupDocuments($group, $scan), 'result' => $group];
        });
    }

    public function receipt(string $directory, string $ref, FeedbackReceipt $receipt): array
    {
        return $this->mutate($directory, function (array $config, array $scan) use ($directory, $ref, $receipt): array {
            $group = $this->optionalDocument($directory, 'analyses/' . $ref . '.json');
            if ($group === null || ($group['feedback']['mocked'] ?? null) !== $receipt->mocked) {
                throw new Failure('feedback-response-invalid', 'Receipt does not match the reserved feedback mode.', 1, [], true);
            }

            $this->activeGroup($group, $scan);
            if (isset($group['feedback']['receiptId']) && $group['feedback']['receiptId'] !== $receipt->receiptId) {
                throw new Failure('feedback-response-invalid', 'Reconciliation cannot replace an already confirmed receipt.', 1, [], true);
            }

            $group['feedback'] = [...$group['feedback'], ...$receipt->publicData()];
            $group['state'] = $receipt->mocked ? 'mock-submitted' : 'submitted';
            $documents = $this->groupDocuments($group, $scan);
            if ($receipt->accessSecret !== null) {
                $documents['private/' . $ref . '.json'] = ['accessSecret' => $receipt->accessSecret];
            }

            return ['documents' => $documents, 'result' => $this->publicGroup($group)];
        });
    }

    public function failed(string $directory, string $ref, bool $unknown): array
    {
        return $this->mutate($directory, function (array $config, array $scan) use ($directory, $ref, $unknown): array {
            $group = $this->optionalDocument($directory, 'analyses/' . $ref . '.json');
            if ($group === null) {
                throw Failure::usage('Saved analysis group does not exist.');
            }

            $this->activeGroup($group, $scan);
            if (in_array($group['state'], ['submitted', 'mock-submitted'], true)) {
                return ['result' => $this->publicGroup($group)];
            }

            $group['state'] = $unknown ? 'submission-unknown' : 'submission-pending';
            return ['documents' => $this->groupDocuments($group, $scan), 'result' => $this->publicGroup($group)];
        });
    }

    public function publicGroup(array $group): array
    {
        return array_intersect_key($group, array_flip(['groupRef', 'batchId', 'analysis', 'publication', 'state', 'feedback', 'createdAt']));
    }

    private function activeGroup(array $group, array $scan): void
    {
        foreach ($group['analysis']['cardIds'] as $id) {
            if (($scan['cards'][$id]['analysisRef'] ?? null) !== $group['groupRef']) {
                throw Failure::usage('Group snapshot is no longer active; do not submit superseded or removed cards.');
            }
        }
    }

    private function groupDocuments(array $group, array $scan): array
    {
        $documents = ['analyses/' . $group['groupRef'] . '.json' => $group];
        foreach ($group['analysis']['cardIds'] as $id) {
            $card = $scan['cards'][$id];
            $card['state'] = $group['state'];
            $card['feedback'] = $group['feedback'] ?? null;
            $documents[$id . '.json'] = $card;
        }

        return $documents;
    }

    private function publication(array $group, array $cards): array
    {
        $versions = array_values(array_unique(array_column($cards, 'cliVersion')));
        $skillVersions = array_values(array_unique(array_map(static fn (array $card): string => $card['skillVersion'] ?? 'unknown', $cards)));
        $body = '## Expected' . "\n" . $group['expected'] . "\n\n## Observed limitation\n" . $group['actual'];
        $body .= "\n\n## Synthetic reproduction\n- " . implode("\n- ", $group['syntheticReproduction']);
        $body .= "\n\n## Acceptance criteria\n- " . implode("\n- ", $group['acceptanceCriteria']);
        $body .= "\n\nOwner: " . $group['owner'] . '; confidence: ' . $group['confidence'] . '; local reports: ' . count($cards) . '; CLI versions: ' . implode(', ', $versions);
        $body .= '; skill versions: ' . implode(', ', $skillVersions);
        $body .= "\nReport counts do not measure all agent runs or distinct users.";
        return ['title' => $group['title'], 'body' => $body];
    }

    private function terminal(array $card): bool
    {
        return in_array($card['state'], ['linked-existing', 'dismissed'], true) || ($card['state'] === 'submitted' && ($card['feedback']['delivered'] ?? false) === true && ($card['feedback']['mocked'] ?? true) === false && is_string($card['feedback']['receiptId'] ?? null));
    }

    private function now(): string
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    public function recover(string $directory): array
    {
        return $this->mutate($directory, static fn (array $config, array $scan): array => ['result' => ['recovered' => true]]);
    }

    private function mutate(string $directory, \Closure $operation): array
    {
        $folder = $this->folder($directory);
        return $this->profiles->transaction($directory, function (array &$config) use ($directory, $folder, $operation): array {
            $this->protection->noLinks($folder . '/.journal');
            if (file_exists($folder . '/.journal')) {
                $recovery = $this->document($folder . '/.journal', 16777216);
                $this->applyPlan($directory, $recovery, $config);
            }

            $plan = $operation($config, $this->scan($directory));
            $config['telemetry'] = TelemetrySettings::effective([...($config['telemetry'] ?? []), ...($plan['telemetry'] ?? [])]);
            $journal = ['documents' => $plan['documents'] ?? [], 'delete' => $plan['delete'] ?? [], 'telemetry' => $config['telemetry']];
            $journal['delete'] = [...$journal['delete'], ...$this->orphanedAnalyses($directory, $journal)];
            $this->protection->directory($folder);
            $this->publish($folder . '/.journal', $journal);
            $this->applyPlan($directory, $journal, $config);
            return $plan['result'];
        }, function () use ($folder): void {
            $this->protection->check($folder . '/.journal');
            if (!@unlink($folder . '/.journal')) {
                throw new Failure('storage-recovery-required', 'Committed storage journal needs recovery cleanup.');
            }
        });
    }

    private function applyPlan(string $directory, array $journal, array &$config): void
    {
        if (array_diff(array_keys($journal), ['documents', 'delete', 'telemetry']) !== [] || !is_array($journal['documents'] ?? null) || !is_array($journal['delete'] ?? null) || !is_array($journal['telemetry'] ?? null)) {
            throw new Failure('storage-recovery-required', 'Storage journal has an unsupported structure.');
        }

        TelemetrySettings::validate($journal['telemetry']);
        foreach ($journal['documents'] as $relative => $document) {
            $this->relativePath($relative);
            if (!is_array($document)) {
                throw new Failure('storage-recovery-required', 'Storage journal document is invalid.');
            }
        }

        foreach ($journal['delete'] as $relative) {
            $this->relativePath($relative);
        }

        foreach ($journal['documents'] as $relative => $document) {
            $this->publish($this->folder($directory) . '/' . $relative, $document);
        }

        foreach ($journal['delete'] as $relative) {
            $path = $this->folder($directory) . '/' . $relative;
            $this->protection->noLinks($path);
            if (file_exists($path)) {
                $this->protection->check($path);
                if (!@unlink($path)) {
                    throw new Failure('storage-error', 'Cannot finish terminal card cleanup; the journal is retained.');
                }
            }
        }

        $config['telemetry'] = TelemetrySettings::effective([...($config['telemetry'] ?? []), ...$journal['telemetry']]);
    }

    private function orphanedAnalyses(string $directory, array $plan): array
    {
        $folder = $this->folder($directory) . '/analyses';
        $this->protection->noLinks($folder);
        if (!is_dir($folder)) {
            return [];
        }

        $this->protection->check($folder);
        $live = [];
        foreach ($this->scan($directory)['cards'] as $id => $card) {
            if (in_array($id . '.json', $plan['delete'], true)) {
                continue;
            }

            $effective = $plan['documents'][$id . '.json'] ?? $card;
            if (isset($effective['analysisRef'])) {
                $live[] = $effective['analysisRef'];
            }
        }

        $delete = [];
        foreach (@scandir($folder) ?: [] as $file) {
            if (preg_match('/^([a-f0-9]{64})\.json$/D', $file, $matches) !== 1 || in_array($matches[1], $live, true) || isset($plan['documents']['analyses/' . $file])) {
                continue;
            }

            try {
                $group = $this->optionalDocument($directory, 'analyses/' . $file);
                if ($group !== null) {
                    $delete[] = 'analyses/' . $file;
                    $private = 'private/' . $file;
                    $this->protection->noLinks($this->folder($directory) . '/' . $private);
                    if (file_exists($this->folder($directory) . '/' . $private)) {
                        $delete[] = $private;
                    }
                }
            } catch (\Throwable) {
                // Damaged metadata is retained for explicit recovery, never silently deleted.
            }
        }

        return $delete;
    }

    private function scan(string $directory): array
    {
        $folder = $this->folder($directory);
        $this->protection->noLinks($folder);
        if (!file_exists($folder)) {
            return ['cards' => [], 'count' => 0, 'corruptCount' => 0];
        }

        $this->protection->check($folder);
        $files = @scandir($folder);
        if ($files === false) {
            throw new Failure('storage-error', 'Cannot inspect private problem trajectory storage.');
        }

        $cards = [];
        $count = 0;
        $corrupt = 0;
        foreach ($files as $file) {
            if (!str_ends_with($file, '.json')) {
                continue;
            }

            $count++;
            $this->protection->check($folder . '/' . $file);
            try {
                if (preg_match('/^([a-f0-9]{32})\.json$/D', $file, $matches) !== 1) {
                    throw Failure::usage('Invalid card filename.');
                }

                $card = $this->document($folder . '/' . $file, 131072);
                $fields = ['schemaVersion', 'sanitizedScenario', 'availableCommands', 'missingCapability', 'wrapperAlgorithm', 'expectedOutcome', 'cliVersion', 'skillVersion'];
                if (array_diff(array_keys($card), [...$fields, 'cardId', 'createdAt', 'state', 'reportedCliVersion', 'payloadHash', 'registrationHash', 'analysisRef', 'feedback']) !== []) {
                    throw Failure::usage('Unknown saved card field.');
                }

                ProblemTrajectoryInput::report(array_intersect_key($card, array_flip($fields)));
                ProblemTrajectoryInput::identifier($card['reportedCliVersion'] ?? null, 128, true);
                if (($card['cardId'] ?? null) !== $matches[1] || !TelemetrySettings::timestamp($card['createdAt'] ?? null) || !in_array($card['state'] ?? null, ProblemTrajectoryInput::STATES, true) || preg_match('/^[a-f0-9]{64}$/D', $card['payloadHash'] ?? '') !== 1) {
                    throw Failure::usage('Invalid saved card metadata.');
                }

                if (($card['registrationHash'] ?? null) !== null && preg_match('/^[a-f0-9]{64}$/D', $card['registrationHash']) !== 1) {
                    throw Failure::usage('Invalid registration metadata.');
                }

                if (isset($card['analysisRef'])) {
                    ProblemTrajectoryInput::selection(['groupRefs' => [$card['analysisRef']]]);
                    if ($this->optionalDocument($directory, 'analyses/' . $card['analysisRef'] . '.json') === null) {
                        throw Failure::usage('Saved analysis reference is missing.');
                    }
                } elseif ($card['state'] !== 'pending') {
                    throw Failure::usage('Processed card requires a saved analysis.');
                }

                if (isset($card['feedback'])) {
                    $this->validateFeedback($card['feedback']);
                }

                $cards[$card['cardId']] = $card;
            } catch (\Throwable) {
                $corrupt++;
            }
        }

        uasort($cards, static fn (array $left, array $right): int => [$left['createdAt'], $left['cardId']] <=> [$right['createdAt'], $right['cardId']]);
        return ['cards' => $cards, 'count' => $count, 'corruptCount' => $corrupt];
    }

    private function validateFeedback(mixed $feedback): void
    {
        if (!is_array($feedback) || array_diff(array_keys($feedback), ['idempotencyKey', 'mocked', 'delivered', 'receiptId']) !== [] || !is_bool($feedback['mocked'] ?? null) || !is_bool($feedback['delivered'] ?? null) || preg_match('/^[a-f0-9]{64}$/D', $feedback['idempotencyKey'] ?? '') !== 1) {
            throw Failure::usage('Invalid saved feedback metadata.');
        }

        if (($feedback['receiptId'] ?? null) !== null) {
            new FeedbackReceipt($feedback['receiptId'], $feedback['mocked']);
        }
    }

    private function optionalDocument(string $directory, string $relative): ?array
    {
        $this->relativePath($relative);
        $path = $this->folder($directory) . '/' . $relative;
        $this->protection->noLinks($path);
        if (!file_exists($path)) {
            return null;
        }

        $document = $this->document($path, 1048576);
        if (str_starts_with($relative, 'analyses/')) {
            if (array_diff(array_keys($document), ['groupRef', 'batchId', 'fingerprint', 'analysis', 'state', 'publication', 'createdAt', 'feedback']) !== [] || !is_array($document['analysis'] ?? null) || !is_array($document['publication'] ?? null)) {
                throw new Failure('storage-error', 'Saved analysis is damaged.');
            }

            ProblemTrajectoryInput::batch(['schemaVersion' => 1, 'batchId' => $document['batchId'] ?? null, 'groups' => [$document['analysis']]]);
            if (array_keys($document['publication']) !== ['title', 'body']) {
                throw new Failure('storage-error', 'Saved publication is damaged.');
            }

            ProblemTrajectoryInput::text($document['publication']['title'], 256);
            ProblemTrajectoryInput::text($document['publication']['body'], ProblemTrajectoryInput::MAX_BATCH_BYTES);
            if (($document['groupRef'] ?? null) !== substr($relative, 9, -5) || ($document['fingerprint'] ?? null) !== hash('sha256', json_encode($document['analysis'], JSON_THROW_ON_ERROR)) || !in_array($document['state'] ?? null, ProblemTrajectoryInput::STATES, true) || !TelemetrySettings::timestamp($document['createdAt'] ?? null)) {
                throw new Failure('storage-error', 'Saved analysis metadata is damaged.');
            }

            if (isset($document['feedback'])) {
                $this->validateFeedback($document['feedback']);
            }
        }

        return $document;
    }

    private function relativePath(mixed $relative): void
    {
        if (!is_string($relative) || preg_match('~^(?:[a-f0-9]{32}\.json|(?:analyses|private)/[a-f0-9]{64}\.json)$~D', $relative) !== 1) {
            throw new Failure('storage-error', 'Invalid private storage document reference.');
        }
    }

    private function document(string $path, int $maximum): array
    {
        $this->protection->check($path);
        $data = is_file($path) ? @file_get_contents($path, false, null, 0, $maximum + 1) : false;
        if ($data === false || strlen($data) > $maximum) {
            throw new Failure('storage-error', 'Private storage document is unreadable or exceeds its size limit.');
        }

        try {
            if (!json_decode($data, false, 64, JSON_THROW_ON_ERROR) instanceof \stdClass) {
                throw new \RuntimeException();
            }

            return json_decode($data, true, 64, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new Failure('storage-error', 'Private storage document is damaged.');
        }
    }

    private function publish(string $path, array $data): void
    {
        $this->protection->directory(dirname($path));
        $this->protection->noLinks($path);
        if (file_exists($path)) {
            $this->protection->check($path);
        }

        $temporary = dirname($path) . '/.write-' . bin2hex(random_bytes(16));
        $stream = $this->protection->create($temporary);
        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
            if (fwrite($stream, $json) !== strlen($json) || !fflush($stream) || (function_exists('fsync') && !fsync($stream))) {
                throw new \RuntimeException();
            }

            fclose($stream);
            $stream = null;
            $this->protection->noLinks($path);
            if (!@rename($temporary, $path)) {
                throw new \RuntimeException();
            }
        } catch (\Throwable) {
            throw new Failure('storage-error', 'Cannot atomically publish private storage; recovery information is retained.');
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }

            if (file_exists($temporary)) {
                @unlink($temporary);
            }
        }
    }

    private function read(string $directory, \Closure $operation): array
    {
        return $this->profiles->readTransaction($directory, function (array $config) use ($directory, $operation): array {
            $this->protection->noLinks($this->folder($directory) . '/.journal');
            if (file_exists($this->folder($directory) . '/.journal')) {
                throw new Failure('storage-recovery-required', 'An interrupted storage transaction requires a mutating doctor action to recover before reading.');
            }

            return $operation($config, $this->scan($directory));
        });
    }

    private function folder(string $directory): string
    {
        return $directory . '/.problem-trajectories';
    }
}
