# Spec Delta

## Purpose

Обеспечить независимое выполнение задач разработки в нескольких Git worktree одного репозитория на одном хосте без конфликтов окружений и взаимного изменения локального состояния.

## ADDED Requirements

### Requirement: Stable checkout identity
The development environment SHALL assign a stable, distinct Compose project identity to each checkout on the same host without requiring manual configuration for the normal workflow.

#### Scenario: Worktrees with matching directory names
- **WHEN** two worktrees with the same directory basename but different absolute paths start their environments
- **THEN** their Compose project identities differ
- **AND** repeated commands in either checkout address its own project

### Requirement: Independent build and runtime resources
The development environment SHALL isolate mutable Docker resources and build image tags between checkouts so that build, start, restart and shutdown in one checkout do not change the runtime of another checkout.

#### Scenario: Parallel builds and startup
- **WHEN** two worktrees build their PHP images and start containers concurrently
- **THEN** each environment uses the image built for its checkout and mounts that checkout's files
- **AND** both environments remain operational without naming conflicts

#### Scenario: Stop one environment
- **WHEN** one worktree stops or restarts its environment while another is running
- **THEN** only the selected worktree's resources are stopped or restarted
- **AND** the other worktree continues to run CLI commands successfully

### Requirement: Worktree-local writable state
The development environment SHALL keep dependencies, generated files and local configuration within the selected checkout and SHALL NOT implicitly reuse or overwrite another checkout's writable state or credentials.

#### Scenario: Install and modify local files
- **WHEN** dependencies are installed or a local file is changed in worktree A
- **THEN** worktree B's dependencies and local files remain unchanged
- **AND** worktree A's bind-mounted files are created with the invoking user's UID/GID

### Requirement: Existing Make workflow compatibility
The development environment SHALL support the existing setup, Composer, CLI, check and container lifecycle Make commands in both the primary checkout and a worktree without requiring locally installed PHP or Composer.

#### Scenario: Fresh worktree setup
- **WHEN** a developer runs the documented setup in a fresh worktree and executes `make cli` and `make check`
- **THEN** dependencies are installed in that checkout and both commands succeed with Docker, Docker Compose and Make

### Requirement: Documented setup and safe cleanup
The repository SHALL document worktree creation, environment preparation, parallel execution and cleanup, SHALL ignore the documented project-local worktree directory and generated environment metadata, and SHALL require explicit user action for copying local secrets.

#### Scenario: Remove a worktree environment
- **WHEN** a developer follows the documented cleanup for one worktree
- **THEN** its environment is stopped before checkout removal
- **AND** another worktree's containers, dependencies and files are preserved

### Requirement: Reproducible concurrent acceptance check
The repository SHALL provide a reproducible check using two simultaneous worktrees that verifies resource identity, file and dependency isolation, CLI execution and shutdown independence, and cleans up only resources it created.

#### Scenario: Concurrent acceptance run
- **WHEN** the acceptance check runs against two distinct worktrees on one Docker host
- **THEN** it verifies distinct project and image identities, checkout-specific mounts and unchanged sentinel files in the other checkout
- **AND** CLI commands succeed in both before shutdown and in the surviving checkout after the first is stopped
- **AND** failure output identifies the failed isolation check without exposing credentials
