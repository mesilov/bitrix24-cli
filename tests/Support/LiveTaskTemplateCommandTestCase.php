<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Support;

use Bitrix24\CLI\Infrastructure\Bitrix24\Rest1TaskTemplateAdapter;

abstract class LiveTaskTemplateCommandTestCase extends LiveCommandTestCase
{
    protected Rest1TaskTemplateAdapter $templates;
    protected array $createdTemplates = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->templates = new Rest1TaskTemplateAdapter($this->api);
    }

    protected function templateFields(): array
    {
        return ['TITLE' => 'b24cli-template-' . bin2hex(random_bytes(8)), 'CREATED_BY' => $this->userId, 'RESPONSIBLE_ID' => $this->userId];
    }

    protected function createTemplate(): int
    {
        $id = $this->templates->add($this->templateFields());
        $this->createdTemplates[] = $id;
        return $id;
    }

    protected function afterInvoke(array $argv, array $json): void
    {
        if ($argv[0] === 'task:template:add' && isset($json['data']['operation']['templateId'])) {
            $this->createdTemplates[] = $json['data']['operation']['templateId'];
        }
    }

    protected function tearDown(): void
    {
        $failed = [];
        foreach ($this->createdTemplates as $id) {
            try {
                if ($this->templates->get($id) !== null) {
                    $this->templates->delete($id);
                    if ($this->templates->get($id) !== null) {
                        $failed[] = $id;
                    }
                }
            } catch (\Throwable) {
                $failed[] = $id;
            }
        }

        try {
            parent::tearDown();
        } finally {
            self::assertSame([], $failed, 'Template fixture cleanup could not be observed; inspect these owned IDs: ' . implode(',', $failed));
        }
    }
}
