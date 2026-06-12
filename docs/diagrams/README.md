# NOVA — Architecture & Database Diagrams

All diagrams are Mermaid-in-markdown: they render directly on GitHub and diff like code.

| File | What it shows |
|---|---|
| [application-layers.md](application-layers.md) | The DDD request flow every feature follows (Route → Controller → FormRequest → Service → Repository → Model) |
| [domain-map.md](domain-map.md) | The bounded contexts under `app/Domain/` and their real dependencies (derived from `use` statements) |
| [infrastructure.md](infrastructure.md) | Deployment topology — local Docker stack and production |
| [flows/billing-generate.md](flows/billing-generate.md) | Daily billing automation sequence (Standard + Advance) |
| [flows/makeup-request.md](flows/makeup-request.md) | School closure → make-up email → parent self-reschedule |
| [flows/invoice-lifecycle.md](flows/invoice-lifecycle.md) | Invoice draft → sent → paid → ledger |
| [erd/](erd/README.md) | Entity-relationship diagrams, one file per domain group — **generated**, see below |

## Regenerating the ERD

The ERD files are generated from the live database schema so they can never drift:

```bash
make erd          # runs: php artisan diagrams:erd (inside Docker)
make erd-check    # CI-style: fails if committed ERDs differ from the schema
```

The generator reads `information_schema` plus the table→group and polymorphic
maps in `config/diagrams.php`. **Adding a migration with a new table requires
adding the table to a group in that config** — the command fails loudly on
unmapped tables, on purpose.

Hand-written prose in the ERD files survives regeneration: only the fenced
block between `<!-- erd:start -->` and `<!-- erd:end -->` is rewritten.
