#!/usr/bin/env python3
"""Test copied artifacts without PHP, build tools, checkout files or credentials."""
import argparse
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import json
import os
from pathlib import Path
import shutil
import ssl
import subprocess
import sys
import tempfile
import threading


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
        check(version == "Bitrix24 CLI " + args.expected_version, "Version mismatch: " + version)
        for command in [["list", "--no-ansi"], ["--help", "--no-ansi"]]:
            output = invoke("b24cli", command)
            expected = "Bitrix24 CLI" if command[0] == "list" else "Usage:"
            check(expected in output and "\x1b" not in output, "CLI/help contract failed")
        invoke("b24cli", ["несуществующая-команда", "--no-ansi"], 1, stderr_contains="несуществующая-команда")
        probe = json.loads(invoke("runtime-probe", []))
        check(probe["version"] == args.expected_version, "Probe build identity differs")
        if not args.phar:
            check(probe["php"] == json.loads((dist / "build-info.json").read_text())["php_version"],
                  "Runtime PHP version differs from pinned input")
        check(probe["unicode"] == "Привет, Битрикс24", "Unicode/autoload fixture differs")

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
                          "checks": ["version", "help", "list", "usage-exit", "autoload", "extensions", "unicode",
                                     "trusted-https", "rejected-certificate", "clean-PATH", "temporary-phar-cleanup"]}, ensure_ascii=False))


if __name__ == "__main__":
    main()
