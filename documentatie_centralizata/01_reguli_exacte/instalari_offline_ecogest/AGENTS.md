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