#!/usr/bin/env python3
"""Validate the published schema, shared fixtures and documentation examples."""

import json
from pathlib import Path
import re
import sys

from jsonschema import Draft202012Validator, FormatChecker

ROOT = Path(__file__).resolve().parents[2]


def main() -> int:
    schema = json.loads((ROOT / "docs/config.schema.json").read_text(encoding="utf-8"))
    Draft202012Validator.check_schema(schema)
    validator = Draft202012Validator(schema, format_checker=FormatChecker())
    corpus = json.loads(
        (ROOT / "tests/Fixtures/configuration/schema-cases.json").read_text(encoding="utf-8")
    )
    failures = []
    names = set()
    additional_runtime_checks = 0

    for case in corpus["cases"]:
        name = case["name"]
        if name in names:
            failures.append(f"duplicate fixture name: {name}")
        names.add(name)
        if case["schemaValid"] != case["runtimeValid"]:
            additional_runtime_checks += 1
            if not case.get("reason"):
                failures.append(f"{name}: unexplained schema/runtime difference")
        config = json.loads(case["json"])
        errors = list(validator.iter_errors(config))
        if (not errors) != case["schemaValid"]:
            summary = "; ".join(error.message for error in errors[:3])
            failures.append(f"{name}: expected schemaValid={case['schemaValid']}; {summary}")

    document = (ROOT / "docs/config.md").read_text(encoding="utf-8")
    examples = re.findall(r"```json\n(.*?)\n```", document, re.DOTALL)
    if not examples:
        failures.append("config.md has no JSON examples")
    for index, example in enumerate(examples, 1):
        errors = list(validator.iter_errors(json.loads(example)))
        if errors:
            failures.append(f"documentation example {index}: {errors[0].message}")

    if failures:
        for failure in failures:
            print(failure, file=sys.stderr)
        return 1

    print(
        f"Configuration schema valid: {len(names)} shared fixtures, "
        f"{len(examples)} documentation examples; "
        f"{additional_runtime_checks} documented additional runtime checks."
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
