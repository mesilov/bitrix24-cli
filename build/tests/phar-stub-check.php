<?php

// Exercise the real loader with controlled micro/temporary-path shims.
$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'b24cli-stub-check-' . bin2hex(random_bytes(12));
mkdir($root);
$temporary = $root . DIRECTORY_SEPARATOR . 'payloads';
mkdir($temporary);
$payload = $root . DIRECTORY_SEPARATOR . 'fixture.phar';
$log = $root . DIRECTORY_SEPARATOR . 'opened.txt';
$runner = $root . DIRECTORY_SEPARATOR . 'runner.php';
$stub = $root . DIRECTORY_SEPARATOR . 'stub.php';

function ensure(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function launch(string $runner, string $temporary, string $payload, string $log): array
{
    $process = proc_open([PHP_BINARY, $runner, $temporary, $payload, $log],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    ensure(is_resource($process), 'Cannot start loader fixture');
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), $stdout, $stderr];
}

try {
    $archive = new Phar($payload);
    $archive['fixture.php'] = '<?php echo "loaded";';
    $archive->setStub('<?php __HALT_COMPILER();');
    $archive->setSignatureAlgorithm(Phar::SHA256);
    $archive->compressFiles(Phar::GZ);
    unset($archive);
    $source = file_get_contents(__DIR__ . '/../phar-stub.php');
    $source = substr($source, strpos($source, '<?php'));
    $source = str_replace(['__B24CLI_PAYLOAD_STRATEGY__', '__B24CLI_ENTRY__'], ['temporary-phar', 'fixture.php'], $source);
    $source = str_replace('<?php', "<?php\nnamespace StubFixture;\nuse Phar;\nuse RuntimeException;\nuse Throwable;", $source);
    file_put_contents($stub, $source);
    file_put_contents($runner, <<<'RUNNER'
<?php
namespace {
    function micro_open_self() { return fopen($GLOBALS['argv'][2], 'rb'); }
    function micro_get_sfxsize(): int { return 0; }
}
namespace StubFixture {
    function sys_get_temp_dir(): string { return $GLOBALS['argv'][1]; }
    function random_bytes(int $length): string { return str_repeat("\0", $length); }
    function fopen(string $path, string $mode) {
        file_put_contents($GLOBALS['argv'][3], $path);
        return \fopen($path, $mode);
    }
    require __DIR__ . '/stub.php';
}
RUNNER);
    [$exit, $stdout, $stderr] = launch($runner, $temporary, $payload, $log);
    ensure($exit === 0 && $stdout === 'loaded' && $stderr === '', 'Payload execution failed: ' . $stdout . $stderr);
    ensure(glob($temporary . DIRECTORY_SEPARATOR . '*') === [], 'Temporary PHAR was not cleaned');
    $occupied = file_get_contents($log);
    // The loader now atomically reserves a directory before opening its PHAR.
    mkdir(dirname($occupied));
    file_put_contents($occupied, 'existing-file-sentinel');
    [$exit, $stdout, $stderr] = launch($runner, $temporary, $payload, $log);
    ensure($exit !== 0 && str_contains($stdout . $stderr, 'Cannot create temporary application PHAR.'), 'Occupied path did not fail');
    ensure(file_get_contents($occupied) === 'existing-file-sentinel', 'Occupied path was overwritten or deleted');
    unlink($occupied);
    rmdir(dirname($occupied));
    [$exit, $stdout, $stderr] = launch($runner, $root . '/missing/directory', $payload, $log);
    ensure($exit !== 0 && str_contains($stdout . $stderr, 'Cannot create temporary application PHAR.'), 'Unavailable temporary directory did not fail');
    echo "Verified 3 PHAR loader regressions: payload/cleanup, exclusive collision, unavailable temp\n";
} finally {
    if (is_file($payload)) {
        Phar::unlinkArchive($payload);
    }
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($root);
}
