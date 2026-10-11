<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Connection;

use Bitrix24\CLI\Application\Failure;

final class FileProtection
{
    public function directory(string $path): void
    {
        $this->noLinks($path);
        if (!is_dir($path)) {
            $parent = dirname($path);
            if (!is_dir($parent)) {
                $this->directory($parent);
            }

            $mask = umask(0077);
            try {
                if (!@mkdir($path, 0700) && !is_dir($path)) {
                    throw $this->failure();
                }
            } finally {
                umask($mask);
            }

            $this->check($path, true);
        } else {
            $this->check($path);
        }
    }

    public function create(string $path): mixed
    {
        $this->noLinks($path);
        $mask = umask(0077);
        try {
            $stream = @fopen($path, 'x+b');
        } finally {
            umask($mask);
        }

        if ($stream === false) {
            throw $this->failure();
        }

        try {
            $this->check($path, true);
        } catch (\Throwable $error) {
            fclose($stream);
            @unlink($path);
            throw $error;
        }

        return $stream;
    }

    public function check(string $path, bool $initialize = false): void
    {
        $this->noLinks($path);
        clearstatcache(true, $path);
        if (!file_exists($path)) {
            throw $this->failure();
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $this->windows($path, $initialize);
            return;
        }

        $stat = @lstat($path);
        if ($stat === false || ($stat['mode'] & 0077) !== 0 || (function_exists('posix_geteuid') && $stat['uid'] !== posix_geteuid())) {
            throw $this->failure();
        }
    }

    public function noLinks(string $path): void
    {
        for ($part = $path; $part !== dirname($part); $part = dirname($part)) {
            if (is_link($part)) {
                throw $this->failure();
            }
        }
    }

    private function windows(string $path, bool $initialize): void
    {
        // Use the Windows inbox API. Paths are data in the child environment, never script fragments.
        $script = <<<'PS'
$ErrorActionPreference = 'Stop'
$p = $env:B24CLI_ACL_PATH
$sid = [System.Security.Principal.WindowsIdentity]::GetCurrent().User
if ($env:B24CLI_ACL_INITIALIZE -eq '1') {
    if ([System.IO.Directory]::Exists($p)) {
        $acl = New-Object System.Security.AccessControl.DirectorySecurity
        $rule = New-Object System.Security.AccessControl.FileSystemAccessRule($sid, 'FullControl', 'ContainerInherit,ObjectInherit', 'None', 'Allow')
    } else {
        $acl = New-Object System.Security.AccessControl.FileSecurity
        $rule = New-Object System.Security.AccessControl.FileSystemAccessRule($sid, 'FullControl', 'Allow')
    }
    $acl.SetOwner($sid)
    $acl.SetAccessRuleProtection($true, $false)
    $acl.AddAccessRule($rule)
    Set-Acl -LiteralPath $p -AclObject $acl
}
$actual = Get-Acl -LiteralPath $p
if (!$actual.AreAccessRulesProtected -or $actual.GetOwner([System.Security.Principal.SecurityIdentifier]).Value -ne $sid.Value) { exit 1 }
$rules = $actual.GetAccessRules($true, $true, [System.Security.Principal.SecurityIdentifier])
if ($rules.Count -eq 0) { exit 1 }
foreach ($r in $rules) {
    if ($r.IdentityReference.Value -ne $sid.Value -or $r.AccessControlType -ne 'Allow' -or ($r.FileSystemRights -band [System.Security.AccessControl.FileSystemRights]::FullControl) -ne [System.Security.AccessControl.FileSystemRights]::FullControl) { exit 1 }
}
PS;
        $executable = (getenv('SystemRoot') ?: getenv('SYSTEMROOT')) . '\\System32\\WindowsPowerShell\\v1.0\\powershell.exe';
        $environment = [...getenv(), 'B24CLI_ACL_PATH' => $path, 'B24CLI_ACL_INITIALIZE' => $initialize ? '1' : '0'];
        $process = @proc_open([$executable, '-NoProfile', '-NonInteractive', '-EncodedCommand', base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'))], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $environment);
        if (!is_resource($process)) {
            throw $this->failure();
        }

        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw $this->failure();
        }
    }

    private function failure(): Failure
    {
        return new Failure('configuration-error', 'Cannot establish or verify private configuration storage; check ownership, permissions and symbolic links.');
    }
}
