# Spec Delta

## Purpose

Определяет будущую возможность работы с шаблонами задач через CLI; область выделена отдельно и остаётся отложенной до проверки официальных API-контрактов и согласования сценариев.

## ADDED Requirements

### Requirement: Template workflows need a verified contract
Будущие CLI-сценарии шаблонов SHALL опираться на официальную документацию доступных REST методов, сохранять API имена полей, права текущего подключения и общий JSON/plain/dry-run контракт. Конкретные команды и payload SHALL быть уточнены в этом change до реализации.

#### Scenario: Start the deferred issue
- **WHEN** пользователь отдельно поручает реализацию #72
- **THEN** сначала уточняются сценарии и контракты через Bitrix24 documentation MCP, обновляются design/spec/tasks, затем выполняется реализация
