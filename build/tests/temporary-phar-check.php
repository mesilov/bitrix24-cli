<?php

declare(strict_types=1);

$root = sys_get_temp_dir() . '/b24cli-temp-check-' . bin2hex(random_bytes(16));
$temporary = $root . '/payloads';
mkdir($temporary, 0700, true);
try {
    $template = file_get_contents(__DIR__ . '/../phar-stub.php');
    foreach (['success', 'entry-failure', 'source-failure'] as $mode) {
        $archive = new Phar($root . '/' . $mode . '.phar');
        $archive['fixture.php'] = $mode === 'entry-failure'
            ? '<?php throw new RuntimeException("Controlled entry failure.");'
            : '<?php echo "Temporary payload fixture passed";';
        $helpers = 'function micro_open_self() { return ' . ($mode === 'source-failure' ? 'false' : 'fopen(__FILE__, "rb")') . '; } function micro_get_sfxsize() { return 0; }';
        $stub = str_replace(['__B24CLI_PAYLOAD_STRATEGY__', '__B24CLI_ENTRY__'], ['temporary-phar', 'fixture.php'], $template);
        $archive->setStub(str_replace('<?php', '<?php ' . $helpers, $stub));
        $archive->setSignatureAlgorithm(Phar::SHA256);
        unset($archive);
    }
    $environment = [...getenv(), 'TMPDIR' => $temporary, 'TMP' => $temporary, 'TEMP' => $temporary];
    $start = static function (string $mode) use ($root, $environment): array {
        $process = proc_open([PHP_BINARY, $root . '/' . $mode . '.phar'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $environment);
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start payload fixture.');
        }
        fclose($pipes[0]);
        return [$process, $pipes];
    };
    $finish = static function (array $child, string $mode): void {
        [$process, $pipes] = $child;
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        $expected = match ($mode) {
            'success' => 'Temporary payload fixture passed',
            'entry-failure' => 'Controlled entry failure.',
            'source-failure' => 'Cannot read embedded application PHAR.',
        };
        if (($mode === 'success' ? $status !== 0 : $status === 0) || !str_contains($output, $expected)) {
            throw new RuntimeException('Payload fixture failed: ' . $mode . "\n" . $output);
        }
    };
    foreach (['success', 'entry-failure', 'source-failure'] as $mode) {
        $finish($start($mode), $mode);
        if (glob($temporary . '/*') !== []) {
            throw new RuntimeException('Payload state remained after ' . $mode);
        }
    }
    $children = [];
    for ($i = 0; $i < 4; ++$i) {
        $children[] = $start('success');
    }
    foreach ($children as $child) {
        $finish($child, 'success');
    }
    if (glob($temporary . '/*') !== []) {
        throw new RuntimeException('Concurrent payload state remained.');
    }
    echo "Temporary PHAR success, entry/source failure cleanup and four concurrent processes: PASS\n";
} finally {
    foreach (glob($root . '/*.phar') as $path) {
        Phar::unlinkArchive($path);
    }
    rmdir($temporary);
    rmdir($root);
}
