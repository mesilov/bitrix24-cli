#!/usr/bin/env python3
"""Validate the doctor input schema and published synthetic examples."""

import json
from pathlib import Path
import re

from jsonschema import Draft202012Validator

ROOT = Path(__file__).resolve().parents[2]
schema = json.loads((ROOT / "docs/problem-trajectories.schema.json").read_text())
Draft202012Validator.check_schema(schema)
validator = Draft202012Validator(schema)
examples = re.findall(r"```json\n(.*?)\n```", (ROOT / "docs/problem-trajectories.md").read_text(), re.DOTALL)
assert len(examples) == 2
for example in examples:
    validator.validate(json.loads(example))

report = json.loads(examples[0])
for field, value in [("schemaVersion", 2), ("availableCommands", {}), ("wrapperAlgorithm", []), ("skillVersion", 3), ("availableCommands", ["task:list --json"]), ("rawOutput", "excluded")]:
    candidate = {**report, field: value}
    assert not validator.is_valid(candidate), field

batch = json.loads(examples[1])
for field, value in [("owner", "other"), ("cardIds", []), ("deduplication", True), ("issueLinks", ["https://unrelated.invalid/"]), ("decision", "linked-existing")]:
    candidate = {**batch, "groups": [{**batch["groups"][0], field: value}]}
    assert not validator.is_valid(candidate), field

print("Doctor input schema valid: 2 published examples, 11 negative structural cases.")
