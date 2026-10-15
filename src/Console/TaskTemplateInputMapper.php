<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Task\Request\TaskTemplateRequest;
use Symfony\Component\Console\Input\InputInterface;

final readonly class TaskTemplateInputMapper
{
    private const IDS = ['CREATED_BY', 'RESPONSIBLE_ID', 'GROUP_ID', 'PARENT_ID'];
    private const LISTS = ['ACCOMPLICES', 'AUDITORS'];
    private const FLAGS = ['ALLOW_CHANGE_DEADLINE', 'ALLOW_TIME_TRACKING', 'TASK_CONTROL', 'ADD_IN_REPORT'];
    private const OPTIONS = ['title' => 'TITLE', 'description' => 'DESCRIPTION', 'creator' => 'CREATED_BY', 'responsible' => 'RESPONSIBLE_ID', 'group-id' => 'GROUP_ID'];

    public function __construct(private InputSourceReader $reader, private TaskInputMapper $numbers)
    {
    }

    public function map(InputInterface $input, string $action): TaskTemplateRequest
    {
        $id = $input->hasArgument('TEMPLATE_ID') ? $this->numbers->id($input->getArgument('TEMPLATE_ID'), 'TEMPLATE_ID') : null;
        if (!in_array($action, ['add', 'update'], true)) {
            return new TaskTemplateRequest($id);
        }

        $inline = $input->getOption('fields');
        $path = $input->getOption('fields-file');
        if ($inline !== null && $path !== null) {
            throw Failure::usage('Choose --fields or --fields-file.');
        }

        $fields = $inline !== null || $path !== null ? $this->reader->uniqueObject($inline ?? $this->reader->read($path)) : [];
        foreach (self::OPTIONS as $option => $field) {
            $value = $input->getOption($option);
            if ($value === null) {
                continue;
            }

            if (array_key_exists($field, $fields)) {
                throw Failure::usage('Template field supplied twice: ' . $field . '.');
            }

            $fields[$field] = in_array($field, self::IDS, true) ? $this->numbers->id($value, '--' . $option) : $value;
        }

        foreach ($fields as $field => $value) {
            $valid = match (true) {
                $field === 'TITLE' => is_string($value) && preg_match('//u', $value) === 1 && preg_match('/\S/u', $value) === 1,
                $field === 'DESCRIPTION' => is_string($value) && preg_match('//u', $value) === 1,
                in_array($field, self::IDS, true) => is_int($value) && $value >= (in_array($field, ['GROUP_ID', 'PARENT_ID'], true) ? 0 : 1),
                in_array($field, self::LISTS, true) => is_array($value) && array_is_list($value) && array_all($value, static fn ($id): bool => is_int($id) && $id > 0),
                in_array($field, self::FLAGS, true) => in_array($value, ['Y', 'N'], true),
                default => false,
            };
            if (!$valid) {
                throw Failure::usage('Unsupported template field or value: ' . $field . '. Use native REST 1.0 names and JSON types; see command help.');
            }
        }

        if ($fields === [] || ($action === 'add' && array_diff(['TITLE', 'CREATED_BY', 'RESPONSIBLE_ID'], array_keys($fields)) !== [])) {
            throw Failure::usage($action === 'add' ? 'Template creation needs TITLE, CREATED_BY and RESPONSIBLE_ID.' : 'Supply at least one template field to update.');
        }

        return new TaskTemplateRequest($id, $fields);
    }
}
