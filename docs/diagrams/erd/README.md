# ERD — Entity-Relationship Diagrams

One file per domain group, **generated** from the live schema by
`php artisan diagrams:erd` (wrapped as `make erd`). Never hand-edit anything
between the `<!-- erd:start -->` / `<!-- erd:end -->` markers — the generator
owns that block. Prose outside the markers is yours; write the non-obvious
facts there (invariants, timezone semantics, polymorphic targets).

## Regenerating

```bash
make erd        # migrate:fresh on bird_test, then rewrite the marker blocks
make erd-check  # same, but fails when committed files are stale (CI-friendly)
```

The test database is used (fresh-migrated) so the ERD always reflects **this
branch's migrations**, never local dev-data drift.

## Maintaining the map

`config/diagrams.php` holds three maps:

- `groups` — table → group file. **A new migration adding a table must add it
  to a group**; the command fails loudly on unmapped tables.
- `exclude` — framework tables (cache, jobs, telescope, …) never drawn.
- `morphs` — polymorphic relations (invisible to FK introspection) drawn as
  dashed edges; update targets when a model gains `HasAudits`, documents, etc.

## Reading conventions

- `||--o{` solid edge: real foreign key inside the group.
- `||..o{` dashed edge: configured polymorphic relation.
- `%% table.col -> other_table (other group)` comment: FK that crosses group
  files — follow it to the named group.
- `PK` / `FK` column suffixes come from the database key type (`FK` also marks
  plain indexed/unique columns — MySQL reports them the same way).
