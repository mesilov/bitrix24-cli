<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Infrastructure\Connection\FileCredentialStore;
use Bitrix24\CLI\Infrastructure\Connection\FileProtection;
use Bitrix24\CLI\Infrastructure\Connection\ProfileRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigSchemaContractTest extends TestCase
{
    #[DataProvider('configurationCases')]
    public function testRuntimeMatchesSharedConfigurationContract(string $json, bool $valid): void
    {
        $directory = sys_get_temp_dir() . '/b24cli-schema-' . bin2hex(random_bytes(12));
        $fileProtection = new FileProtection();
        $fileProtection->directory($directory);

        $path = $directory . '/config.json';
        $stream = $fileProtection->create($path);
        fwrite($stream, $json);
        fclose($stream);
        $profileRepository = new ProfileRepository($fileProtection, new FileCredentialStore($fileProtection));

        try {
            if ($valid) {
                $config = $profileRepository->load($directory);
                self::assertSame(1, $config['version']);
                self::assertArrayHasKey('profiles', $config);
            } else {
                $this->expectException(Failure::class);
                $this->expectExceptionMessage('Invalid config.json');
                $profileRepository->load($directory);
            }
        } finally {
            unlink($path);
            rmdir($directory);
        }
    }

    public static function configurationCases(): iterable
    {
        $content = file_get_contents(__DIR__ . '/../Fixtures/configuration/schema-cases.json');
        if ($content === false) {
            throw new \LogicException('Cannot read shared schema fixtures.');
        }

        $fixtures = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        foreach ($fixtures['cases'] as $case) {
            yield $case['name'] => [$case['json'], $case['runtimeValid']];
        }
    }
}
