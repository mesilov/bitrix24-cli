#!/usr/bin/env php
<?php

// Windows micro stream hooks are bypassed by loading the payload as a regular PHAR.
if ('__B24CLI_PAYLOAD_STRATEGY__' === 'temporary-phar' && function_exists('micro_open_self')) {
    $temporary = tempnam(sys_get_temp_dir(), 'b24cli-payload-');
    if ($temporary === false || !rename($temporary, $temporary . '.phar')) {
        throw new RuntimeException('Cannot create temporary application PHAR.');
    }
    $temporary .= '.phar';
    register_shutdown_function(static function () use ($temporary): void {
        Phar::unlinkArchive($temporary);
    });
    $source = micro_open_self();
    $destination = fopen($temporary, 'wb');
    if ($source === false || $destination === false || fseek($source, micro_get_sfxsize()) !== 0) {
        throw new RuntimeException('Cannot read embedded application PHAR.');
    }
    if (stream_copy_to_stream($source, $destination) === false) {
        throw new RuntimeException('Cannot copy embedded application PHAR.');
    }
    fclose($source);
    fclose($destination);
    require 'phar://' . $temporary . '/__B24CLI_ENTRY__';
} else {
    Phar::mapPhar('b24cli.phar');
    require 'phar://b24cli.phar/__B24CLI_ENTRY__';
}
__HALT_COMPILER();
