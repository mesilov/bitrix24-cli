# Tasks

## 1. Установка скиллов

- [x] 1.1 Скопировать `skills/productivity/grill-me/` и `skills/productivity/grilling/` из mattpocock/skills@`fcf0071560d32913c9d4f820e0d7ca467c881619` в `.agents/skills/` вместе с `agents/openai.yaml`; проверить через `diff` с upstream, что отличий нет.
- [x] 1.2 Добавить в каталог каждого скилла `UPSTREAM.md` (репозиторий, путь, commit) и `LICENSE` upstream; проверить, что commit в `UPSTREAM.md` совпадает с использованным для копирования.

## 2. Проверка

- [x] 2.1 Убедиться, что `grill-me` не помечен для неявного вызова (`disable-model-invocation: true`, `allow_implicit_invocation: false`) и что `openspec validate add-grill-me-skill --strict` проходит.

## Workflow follow-up

- Архивировать change и обновить путь в issue #190.
