# Project Workflow & Engineering Guidelines

This repository enforces strict Pair-Programming and Agentic Engineering standards organized across 4 key pillars:

## 0. Pre-Analysis Mandate (Read before Coding)
- **NO** empieces a tocar archivos basado en tu intuición.
- **SIEMPRE** revisa el grafo en graphify-out/graph.json para entender las dependencias.
- Identifica los controladores y servicios relacionados con el módulo afectado.
- Traza el flujo completo (Request -> Controller -> Service -> Repository -> Database).
- Una vez identificado el punto exacto, aplica la modificaciones necesarias.

## 1. Change Control & Coding Discipline
- **Minimal Diff (Ponytail)**: Touch only the exact lines necessary for a fix or feature. Zero collateral refactoring, zero deletion of existing comments or license headers, and preserve existing formatting style.
- **Conventional Commits**: All commit messages must follow `<type>(<scope>): <subject>` (e.g., `feat(viewmodel): handle async error states`).

## 2. Context & Token Efficiency
- **Token Guard / Lean Context**: Pinpoint target code using `grep_search` before opening files. Use line slice views (`StartLine`/`EndLine`) instead of ingesting multi-hundred-line files whole.
- **Database & Schema Inspection**: Query active schemas, Room JSON definitions, or MCP database connections directly instead of pasting full migration logs.

## 3. UI, Interactions & Design System
- **Interaction Design (Emil Kowalski UI)**: Design microinteractions with spring physics, instant tactile touch feedback (press states, subtle scaling), fluid state transitions, and strict adherence to reduced-motion accessibility.
- **Design Tokens**: Zero magic values. Always consume defined theme tokens (`MaterialTheme.colorScheme`, `MaterialTheme.typography`, standard spacing units).

## 4. Architecture, Contracts & Testing
- **Superpowers (TDD & Plan-Before-Code)**: Plan before coding. For complex state or critical features, outline the plan in an artifact, write the failing test first, and deliver the minimal code to pass.
- **Strict JSON & DTO Contracts**: Enforce explicit nullability, immutable models, and explicit field serialization names (`@SerialName`) across all network and persistence boundaries.

## Registered Workspace Skills
The procedural runbooks for each of these workflows are registered in `.agents/skills/`:
- [minimal-diff](.agents/skills/minimal-diff/SKILL.md)
- [conventional-commits](.agents/skills/conventional-commits/SKILL.md)
- [lean-context](.agents/skills/lean-context/SKILL.md)
- [sql-schema-reader](.agents/skills/sql-schema-reader/SKILL.md)
- [interaction-design](.agents/skills/interaction-design/SKILL.md)
- [design-tokens](.agents/skills/design-tokens/SKILL.md)
- [superpowers](.agents/skills/superpowers/SKILL.md)
- [json-schema-contract](.agents/skills/json-schema-contract/SKILL.md)
