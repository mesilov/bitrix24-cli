#!/usr/bin/env python3
"""Test copied artifacts without PHP, build tools, checkout files or credentials."""
import argparse
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import json
import os
from pathlib import Path
import shutil
import signal
import ssl
import subprocess
import sys
import tempfile
import threading
from concurrent.futures import ThreadPoolExecutor


class Handler(BaseHTTPRequestHandler):
    protocol_version = "HTTP/1.1"

    def do_GET(self):
        body = "Привет, HTTPS".encode()
        self.send_response(200)
        self.send_header("Content-Type", "text/plain; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Connection", "close")
        self.end_headers()
        self.wfile.write(body)

    def log_message(self, *args):
        pass


CERT_CONFIG = """[req]
distinguished_name=dn
[dn]
[server]
subjectAltName=DNS:localhost,IP:127.0.0.1
basicConstraints=critical,CA:TRUE
keyUsage=critical,digitalSignature,keyEncipherment,keyCertSign
extendedKeyUsage=serverAuth
"""


def check(condition, message):
    if not condition:
        raise AssertionError(message)


def main():
    sys.stdout.reconfigure(encoding="utf-8")
    sys.stderr.reconfigure(encoding="utf-8", errors="backslashreplace")
    parser = argparse.ArgumentParser()
    parser.add_argument("dist", type=Path)
    parser.add_argument("--expected-version", required=True)
    parser.add_argument("--phar", action="store_true")
    parser.add_argument("--php", default="php")
    args = parser.parse_args()
    dist = args.dist.resolve()
    suffix = ".phar" if args.phar else (".exe" if os.name == "nt" else "")
    prefix = [str(Path(shutil.which(args.php)).resolve())] if args.phar else []
    with tempfile.TemporaryDirectory(prefix="b24cli-smoke-") as directory:
        clean = Path(directory)
        for name in ["b24cli", "runtime-probe"]:
            shutil.copy2(dist / (name + suffix), clean / (name + suffix))
        # Whitelist OS essentials; no portal credentials, PHP_INI_SCAN_DIR, LD/DYLD paths or build PATH.
        env = {key: value for key, value in os.environ.items()
               if key.upper() in {"SYSTEMROOT", "WINDIR", "TMP", "TEMP", "LANG", "LC_ALL"}}
        env.update({"PATH": str(clean), "NO_COLOR": "1", "TERM": "dumb",
                    "TMPDIR": str(clean), "TMP": str(clean), "TEMP": str(clean)})

        def invoke(name, command, expected=0, stderr_contains=None):
            result = subprocess.run(prefix + [str(clean / (name + suffix)), *command],
                                    cwd=clean, env=env, text=True, encoding="utf-8", errors="backslashreplace",
                                    capture_output=True, timeout=30)
            check(result.returncode == expected, f"{name} {command}: exit {result.returncode}\n{result.stdout}\n{result.stderr}")
            if stderr_contains:
                check(stderr_contains in result.stderr, "Unicode command argument was changed: " + result.stderr)
            return result.stdout

        version = invoke("b24cli", ["--version", "--no-ansi"]).strip()
        check(version == "b24cli " + args.expected_version, "Version mismatch: " + version)
        for command in [["list", "--no-ansi"], ["--help", "--no-ansi"]]:
            output = invoke("b24cli", command)
            expected = "b24cli" if command[0] == "list" else "Usage:"
            check(expected in output and "\x1b" not in output, "CLI/help contract failed")
        unknown = json.loads(invoke("b24cli", ["несуществующая-команда", "--json", "--no-ansi"], 2))
        check(unknown["error"]["code"] == "usage-error" and unknown["meta"]["apiCalls"] == [],
              "Unknown command did not preserve the usage contract")
        commands = [line.split()[0] for line in invoke("b24cli", ["list", "task", "--raw", "--no-ansi"]).splitlines()]
        check(len(commands) == 30 and all(name.startswith("task:") for name in commands),
              "Task command registration differs: " + repr(commands))
        for name in commands:
            check("Usage:" in invoke("b24cli", [name, "--help", "--no-ansi"]), "Task help failed: " + name)
        dry_run = json.loads(invoke("b24cli", ["task:add", "--title", "Привет, Битрикс24", "--creator", "1",
                                               "--responsible", "2", "--dry-run", "--json", "--no-ansi"]))
        check(dry_run["error"] is None and dry_run["meta"]["dryRun"] is True
              and dry_run["meta"]["apiCalls"] == [] and dry_run["meta"]["schemaVersion"] == 1,
              "Offline task dry-run contract failed")
        check(dry_run["data"]["plan"]["steps"] == [{"taskId": None, "step": 1, "method": "tasks.task.add",
              "apiVersion": "3.0", "effect": "write", "fields": {"title": "Привет, Битрикс24", "creatorId": 1,
              "responsibleId": 2}}], "Task DI/UTF-8 arguments or plan changed")
        missing = json.loads(invoke("b24cli", ["task:show", "123", "--json", "--no-ansi"], 1))
        check(missing["error"]["code"] == "connection-unavailable" and missing["meta"]["apiCalls"] == [],
              "Missing connection did not stop before API calls")
        probe = json.loads(invoke("runtime-probe", []))
        check(probe["version"] == args.expected_version, "Probe build identity differs")
        if not args.phar:
            check(probe["php"] == json.loads((dist / "build-info.json").read_text())["php_version"],
                  "Runtime PHP version differs from pinned input")
        check(probe["unicode"] == "Привет, Битрикс24", "Unicode/autoload fixture differs")
        if os.name != 'nt':
            check('pcntl' in probe['extensions'], 'Unix package lacks task cancellation support')
            process = subprocess.Popen(prefix + [str(clean / ('runtime-probe' + suffix)), 'task-signal'],
                                       cwd=clean, env=env, text=True, encoding='utf-8',
                                       stdout=subprocess.PIPE, stderr=subprocess.PIPE)
            try:
                # Bound readiness even when a malformed runtime exits or fails to initialize.
                with ThreadPoolExecutor(max_workers=1) as reader:
                    ready = reader.submit(process.stderr.readline)
                    try:
                        check(ready.result(timeout=10) == 'READY\n', 'Task signal fixture never became ready')
                    except BaseException:
                        process.kill()
                        raise
                process.send_signal(signal.SIGINT)
                stdout, stderr = process.communicate(timeout=10)
                check(process.returncode == 130, 'Task SIGINT exit differs: ' + stdout + stderr)
                interrupted = json.loads(stdout)
                check([item['resourceId'] for item in interrupted['data']['items']] == [50]
                      and [step['status'] for step in interrupted['meta']['ledger']] == ['confirmed', 'failed', 'skipped']
                      and interrupted['error']['code'] == 'interrupted', 'Task SIGINT lost confirmed output')
            finally:
                if process.poll() is None:
                    process.kill()
                process.wait()

        trusted, untrusted = clean / "trusted", clean / "untrusted"
        for path in [trusted, untrusted]:
            path.mkdir()
            (path / "openssl.cnf").write_text(CERT_CONFIG)
            invoke("runtime-probe", ["certificate", str(path)])
        server = ThreadingHTTPServer(("127.0.0.1", 0), Handler)
        context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
        context.load_cert_chain(trusted / "cert.pem", trusted / "key.pem")
        server.socket = context.wrap_socket(server.socket, server_side=True)
        thread = threading.Thread(target=server.serve_forever, daemon=True)
        thread.start()
        url = f"https://127.0.0.1:{server.server_port}/"
        try:
            good = json.loads(invoke("runtime-probe", ["https", url, str(trusted / "cert.pem")]))
            check(good == {"body": "Привет, HTTPS", "errno": 0}, "Trusted HTTPS failed")
            bad = json.loads(invoke("runtime-probe", ["https", url, str(untrusted / "cert.pem")], 1))
            check(bad["body"] is False and bad["errno"] == 60, "Untrusted certificate was not rejected")
        finally:
            server.shutdown()
            server.server_close()
        allowed_phars = {"b24cli.phar", "runtime-probe.phar"} if args.phar else set()
        check(all(path.name in allowed_phars for path in clean.glob("*.phar")),
              "Temporary application PHAR was not removed")
        print(json.dumps({"status": "passed", "version": args.expected_version, "runtime": probe,
                          "checks": ["version", "help", "list", "usage-exit", "30-task-commands", "task-dry-run",
                                     "missing-connection", *( ["task-SIGINT"] if os.name != 'nt' else [] ),
                                     "autoload", "extensions", "unicode",
                                     "trusted-https", "rejected-certificate", "clean-PATH", "temporary-phar-cleanup"]}, ensure_ascii=False))


if __name__ == "__main__":
    main()
