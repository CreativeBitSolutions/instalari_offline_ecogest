# Codex project instructions

## Repository scope

Primary repository:
C:/xampp/htdocs/github/instalari_offline_ecogest

Related repository:
C:/xampp/htdocs/github/agecsin

The primary repository for this workspace is instalari_offline_ecogest.

The agecsin repository may be inspected whenever necessary to understand
the current AGECS implementation, database schema, business logic,
functions, APIs, migrations, or expected application behavior.

Do not modify agecsin unless the user's current request explicitly requires
changes to agecsin.

If the user's request explicitly requires modifications in agecsin,
changes there are allowed.

When a task involves both repositories:
- inspect the relevant implementation in both repositories before changing it;
- determine which repository owns each required change;
- avoid duplicate or unnecessary changes;
- preserve existing behavior and historical data;
- preserve backward compatibility where required;
- keep changes limited to the requested scope;
- clearly distinguish changes made in instalari_offline_ecogest from
  changes made in agecsin.

## Regula de lucru cu fișierele acestui proiect

Pentru acest proiect este permisă modificarea directă a fișierelor țintă.

Nu se creează automat copii intermediare cu sufixe precum `_v2`, `_v3`,
`_v4` sau similare în același folder cu scriptul ori fișierul modificat.

Dacă este necesar un backup, acesta se păstrează numai într-un folder separat
numit `backups`, aflat în folderul proiectului sau al clientului.

Backupul nu se lasă lângă fișierul activ și nu devine sursă de lucru implicită.

Se evită duplicarea inutilă a fișierelor.
Se păstrează doar backupurile necesare pentru recuperare sau cele cerute explicit.

## Astra-Luna orchestration

Do not automatically invoke the astra-orchestrator skill.

Use the astra-orchestrator skill only when the user explicitly invokes:

$astra-orchestrator

Ordinary prompts and follow-up requests must be handled directly by the
root Codex agent without spawning orchestration subagents.

This applies in particular to:
- small fixes;
- localized PHP changes;
- UI and layout changes;
- text changes;
- routine repository inspections;
- simple debugging;
- small database or schema checks;
- follow-up modifications to work already in progress.

When $astra-orchestrator is explicitly invoked, follow the installed
astra-orchestrator skill.

Use specialized subagents only where they materially improve:
- repository exploration;
- implementation quality;
- correctness;
- testing;
- research;
- regression detection;
- independent review.

The root agent owns:
- understanding the requested outcome;
- architecture and implementation direction;
- decomposition of the task;
- integration of subagent work;
- resolution of conflicting findings;
- inspection of the final diff;
- final verification.

Do not delegate trivial work merely for parallelism.

Do not spawn every available role mechanically.

Use only the explorer, worker, tester, researcher, and reviewer roles that
materially improve the current task.

Do not let multiple implementation agents edit the same files concurrently
unless explicit ownership boundaries have been established.

For exploration tasks, prefer read-only work.

For implementation tasks, keep scope and file ownership explicit.

For testing and review, verify the requested behavior and look for
regressions rather than only confirming that files changed.

When an independent review is materially useful, use the configured reviewer
after implementation rather than as a replacement for implementation or testing.

## Autonomous routine work

Proceed autonomously with routine, reversible work that is clearly within
the user's requested scope.

Do not ask the user for approval merely to:
- read files;
- search either repository;
- use rg, grep, git status, git diff, or equivalent inspection commands;
- inspect application structure;
- inspect database schema;
- inspect migrations or ensure-schema logic;
- run safe syntax checks;
- run relevant tests;
- verify changes already made;
- make non-destructive changes explicitly requested by the user.

Ask the user when:
- the requirement is materially ambiguous and different interpretations
  would lead to substantially different outcomes;
- a destructive or irreversible operation is required and was not explicitly requested;
- a breaking database, API, or architectural decision requires a choice that
  cannot safely be inferred;
- credentials, secrets, production access, or another unavailable external
  dependency are required.

## Database and upgrade safety

For database, installer, migration, and ensure-schema work:
- preserve historical data;
- prefer backward-compatible schema changes;
- consider both fresh installations and upgrades of existing installations;
- verify whether agecsin contains a newer implementation that must also be
  reflected in instalari_offline_ecogest;
- do not assume that a solution working on a fresh database is sufficient
  for an existing customer database;
- avoid destructive schema changes unless explicitly required;
- verify upgrade paths when schema changes affect existing installations.

For changes involving both agecsin and instalari_offline_ecogest:
- do not copy code blindly between repositories;
- understand the responsibility of each repository first;
- make the smallest coherent change in the repository that owns the behavior;
- keep installer-specific compatibility logic in instalari_offline_ecogest
  when appropriate.

User instructions always take precedence over this orchestration policy.