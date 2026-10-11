# Packaging attribution

The PHAR → PHP micro → standalone binary pipeline and platform orchestration
are adapted from YiiPress engine, commit e61459fc788a74b91188d5d41a5fca59a02a229e:
https://github.com/yiipress/engine

YiiPress is licensed under BSD-3-Clause. Its license is reproduced in
`YIIPRESS-LICENSE.txt` next to this notice. The application is licensed under
the repository LICENSE. Production Composer package licenses are retained
inside the PHAR. PHP and compiled runtime library licenses are included in
the platform archives under `runtime-licenses/`.
Linux additionally includes the actual musl source COPYRIGHT and GCC 13.2.0
GPLv3 / Runtime Library Exception texts. GCC texts are copied verbatim from
https://github.com/gcc-mirror/gcc/tree/releases/gcc-13.2.0
(`COPYING3` and `COPYING.RUNTIME`); the pinned SPC musl toolchain contains GCC 13.2.0.

static-php-cli: https://github.com/crazywhalecc/static-php-cli (MIT).
PHP micro: https://github.com/static-php/phpmicro (see runtime license dump).

The pinned static-php-cli WindowsBuilder receives the reviewed fake-CLI quoting
patch in `build/patches/windows-micro-fake-cli.json`; its SHA-256 is recorded in
build-info.json. The upstream tool remains under its original license.

## Executable compression

Packaging strategy follows YiiPress 0.1.13: UPX-compressed micro runtime before
PHAR combination on Linux/Windows; wrappe-compressed final executable on macOS.

- UPX 5.2.1: https://github.com/upx/upx/releases/tag/v5.2.1
  GPL with the special exception for compressed executables; complete notices
  from the verified official archive are in compression-licenses/.
- wrappe 1.0.4: https://docs.rs/crate/wrappe/1.0.4
  BSD-2-Clause; license and crate copyright/license notices for its Rust
  launcher are in compression-licenses/. Rust compiler/runtime terms are
  documented at https://www.rust-lang.org/policies/licenses.

Actual version, archive/crate SHA256 and compression settings are recorded in
build-info.json and compression-info.json. Compressor caches remain checkout-local.

The verified wrappe source receives build/patches/wrappe-cargo-home.json to retain
checkout-local CARGO_HOME in its nested runner build; its SHA256 is recorded.
