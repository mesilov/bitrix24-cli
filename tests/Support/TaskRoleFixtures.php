<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Support;

trait TaskRoleFixtures
{
    public static function roleCases(): iterable
    {
        foreach (['responsible', 'creator', 'accomplice', 'auditor'] as $role) {
            yield $role => [$role];
        }
    }

    private function roleTaskPair(string $role, string $title): array
    {
        $otherId = null;
        if (in_array($role, ['responsible', 'creator'], true)) {
            $otherId = filter_var($this->setting('B24CLI_TEST_OTHER_USER_ID'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($otherId === false || $otherId === $this->userId) {
                self::markTestSkipped('Scalar role exclusion needs B24CLI_TEST_OTHER_USER_ID for a different accessible user.');
            }
        }

        $included = $this->createTask($title . ' included');
        $excluded = $this->createTask($title . ' excluded');
        if ($otherId !== null) {
            $field = $role === 'responsible' ? 'responsibleId' : 'creatorId';
            $this->tasks->update($excluded, [$field => $otherId]);
            $relation = $role === 'responsible' ? 'responsible.id' : 'creator.id';
            self::assertSame($this->userId, $this->card($included, [$relation])[$role]['id']);
            self::assertSame($otherId, $this->card($excluded, [$relation])[$role]['id']);
        } else {
            $field = $role === 'accomplice' ? 'accomplices' : 'auditors';
            $this->participants->set($included, $role === 'accomplice' ? [$this->userId] : null, $role === 'auditor' ? [$this->userId] : null);
            $this->participants->set($excluded, $role === 'accomplice' ? [] : null, $role === 'auditor' ? [] : null);
            self::assertContains($this->userId, $this->participants->ids($included, [$field])[$field]);
            self::assertNotContains($this->userId, $this->participants->ids($excluded, [$field])[$field]);
        }

        return [$included, $excluded];
    }
}
