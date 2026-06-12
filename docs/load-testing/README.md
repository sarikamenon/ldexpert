# Load Testing — Schedules & Session Logs at 1M Rows

Repeatable harness for the scalability validation (PRD:
`prd/PRD-scalability-load-testing.md`). **Scope (decided):** the two
highest-volume event tables only — `schedules` and `session_logs` — at
~1,000,000 rows each, built as 10 years of history (2016-06 → 2026-06) on top
of the normal demo seeders. Synthetic data only; never run any of this
against production or real exports.

## 1. Seed

```bash
# Laptop-fast harness iteration (~100k rows/table, < 10 min):
docker compose exec -T app bash -lc 'export DB_DATABASE=bird_test DB_HOST=mysql DB_PORT=3306 && php artisan load-test:seed --scale=tenth'

# Full run (~1M rows/table — run overnight):
docker compose exec -T app bash -lc 'export DB_DATABASE=bird_test DB_HOST=mysql DB_PORT=3306 && php artisan load-test:seed --scale=full'
```

Properties:

- **Idempotent stages** (`base`, `schedules`, `session-logs`, `verify`) — rerun
  resumes; `--stage=` runs a subset after a mid-flight failure.
- **Deterministic** — fixed RNG seed (`--seed`) and a fixed 10-year window, so
  runs are comparable across machines and billing-sweep measurements don't
  drift with the calendar.
- All harness rows carry `notes = 'load-test'`.
- The `verify` stage enforces the invariants (row targets, `session_date` ==
  UTC date of `start_time`, no orphans). **A failed verify = discard the
  dataset**; measurements against a broken graph are noise.
- Session logs are seeded **un-invoiced/un-billed on purpose** — that is the
  worst case for the no-lower-bound billing sweep. Measure `billing:generate`
  with `--dry-run`.

## 2. Measure

App in production-ish mode first (numbers are poisoned otherwise):

```bash
docker compose exec -T app bash -lc 'php artisan config:cache && php artisan route:cache'
```

HTTP latency matrix (DataTables endpoints ×3 variants + page renders):

```bash
BASE_URL=http://localhost:8080 ADMIN_EMAIL=<admin> ADMIN_PASSWORD=<pw> \
  ./scripts/load-test/run-http.sh 20 > _local_docs/load-test-results/$(date +%F)-local.csv
awk -f scripts/load-test/collect.awk _local_docs/load-test-results/<file>.csv
```

Command layer (sweep, make-up scan, ledger verify — wall time + peak RSS):

```bash
./scripts/load-test/run-commands.sh bird_test
```

SQL layer: enable the slow-query log on the MySQL service
(`long_query_time=0.5`), rerun the matrix, then `EXPLAIN` every distinct
query it caught. Full scans/filesorts on the two big tables are the findings.

Afterwards: `php artisan config:clear && php artisan route:clear`.

## 3. Targets (pass/fail — PRD §2)

| Surface | Target |
|---|---|
| List pages (no search) | p95 < 1.5 s |
| List pages (global search) | p95 < 3 s |
| Dashboards / reports | p95 < 2 s |
| `billing:generate` full run | < 15 min, no OOM |
| Any surface | zero 500s / memory-limit errors |

Profile: small team, < 50 concurrent users — single-request latency, not
heavy parallel load. **Archival/pruning is not an acceptable remedy**
(decided) — fixes are indexes, query rewrites, pagination strategy, caching.

## 4. Rounds

1. **Local Docker** — ranks the hotspots (Docker-for-Mac numbers are
   conservative; the *ranking* is the deliverable).
2. **Test server** — identical harness for the pass/fail numbers.

Raw results live in `_local_docs/load-test-results/` (gitignored). Findings
report template: per-surface results vs targets, each failing query with its
`EXPLAIN` and proposed fix, ranked P0 (breaks) / P1 (misses target) / P2.
