# Codex project instructions

For complex coding tasks, use the `astra-orchestrator` skill when its trigger conditions match.

The root agent owns architecture, decomposition, integration, and final verification.
Prefer specialized subagents for bounded exploration, implementation, testing, review, and technical research.

Do not delegate trivial work merely for parallelism.
Do not let multiple implementation agents edit the same files without explicit ownership boundaries.
User instructions always take precedence over this orchestration policy.
## Related AGECS repository

Primary repository:
C:/xampp/htdocs/github/instalari_offline_ecogest

Related repository:
C:/xampp/htdocs/github/agecsin

The primary repository for this workspace is instalari_offline_ecogest.

The agecsin repository may be inspected whenever necessary to understand
the current AGECS implementation, database schema, business logic,
functions, APIs, or expected application behavior.

Do not modify agecsin unless the user's current request explicitly requires
changes to agecsin.

If the user's request explicitly requires modifications in agecsin,
changes there are allowed.

When a task involves both repositories:
- inspect both implementations before changing them;
- determine which repository owns each required change;
- avoid duplicate or unnecessary changes;
- preserve existing behavior and historical data;
- keep changes limited to the requested scope;
- clearly distinguish changes made in instalari_offline_ecogest from
  changes made in agecsin.

## Regula de lucru cu fișierele acestui proiect

Pentru acest proiect este permisă modificarea directă a fișierelor țintă. Nu se creează automat copii intermediare cu sufixe precum `_v2`, `_v3` sau `_v4` în același folder cu scriptul ori fișierul modificat.

Dacă este necesar un backup, acesta se păstrează numai într-un folder separat numit `backups`, aflat în folderul proiectului sau al clientului. Backupul nu se lasă lângă fișierul activ și nu devine sursă de lucru implicită.

Se evită duplicarea inutilă a fișierelor. Se păstrează doar backupurile necesare pentru recuperare sau cele cerute explicit.

## Astra-Luna orchestration

Use the astra-orchestrator skill only when the user explicitly invokes:

$astra-orchestrator

Do not automatically invoke astra-orchestrator for ordinary prompts,
small fixes, UI adjustments, localized PHP changes, routine inspections,
or follow-up modifications.

When $astra-orchestrator is explicitly invoked, use specialized subagents
only where they materially improve correctness, implementation quality,
testing, research, or independent review.

The root agent owns architecture, decomposition, integration, and final
verification.

Do not delegate trivial work merely for parallelism.
Do not let multiple implementation agents edit the same files without
explicit ownership boundaries.

## Repository relationship

Primary repository:
C:/xampp/htdocs/github/instalari_offline_ecogest

Related AGECS repository:
C:/xampp/htdocs/github/agecsin

The agecsin repository may be inspected whenever necessary to understand
the current AGECS implementation, database schema, business logic,
functions, APIs, migrations, and expected behavior.

Do not modify agecsin unless the user's current request explicitly requires
changes there.

When a task involves both repositories:
- inspect the relevant implementation in both repositories first;
- determine which repository owns each required change;
- avoid duplicated functionality;
- preserve existing behavior and historical data;
- preserve backward compatibility where required;
- keep changes limited to the requested scope.

User instructions always take precedence over this orchestration policy.