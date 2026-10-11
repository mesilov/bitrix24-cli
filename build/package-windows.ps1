# Packaging orchestration follows yiipress/engine; see THIRD-PARTY-NOTICES.md.
param([string] $SourceSha = $env:B24CLI_SOURCE_SHA, [string] $Version = $env:B24CLI_RELEASE_VERSION)
$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Push-Location (Join-Path $PSScriptRoot "..")
try {
    $arguments = @("build/package.py", "windows-amd64")
    if ($SourceSha) { $arguments += @("--source-sha", $SourceSha) }
    if ($Version) { $arguments += @("--version", $Version) }
    & python @arguments
    if ($LASTEXITCODE -ne 0) { throw "Windows packaging failed with exit code $LASTEXITCODE" }
} finally {
    Pop-Location
}
