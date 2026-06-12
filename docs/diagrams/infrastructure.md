# Infrastructure Topology

## Local development (from `docker-compose.yml` — authoritative)

```mermaid
flowchart LR
    Dev([Developer browser])

    subgraph DOCKER["docker compose"]
        Nginx["nginx<br/>:8080 → :80"]
        App["app (PHP-FPM)<br/>WORKDIR /var/www/html/app"]
        MySQL[("mysql 8<br/>host :3307 → :3306<br/>databases: bird, bird_test")]
        Chrome["selenium/chrome<br/>(Dusk browser tests)"]
    end

    subgraph HOSTTOOLS["Host-side tooling"]
        Vite["Vite build (make assets-build)<br/>→ public/build manifest"]
        Make["Makefile targets<br/>qa / test / dusk / migrate / erd"]
    end

    subgraph SCHEDULED["Laravel scheduler (routes/console.php)"]
        Cron1["billing:generate — 02:00"]
        Cron2["makeup-reminders:generate — 03:00"]
        Cron3["makeup-reminders:auto-decline — 04:00"]
        Cron4["makeup-reminders:therapist-availability — 06:00"]
        Cron5["makeup-reminders:send-due — 07:00"]
    end

    subgraph EXTERNAL["External services"]
        Stripe["Stripe<br/>public pay-link checkout +<br/>payment_gateway_transactions"]
        SMTP["Outbound mail<br/>invoices, bills, make-up reminders,<br/>credential notifications"]
        Public["Unauthenticated signed URLs<br/>/makeup-response/{token}/* (signed + throttled)<br/>invoice payment_token links"]
    end

    Dev --> Nginx --> App --> MySQL
    Make --> App
    Vite -.->|manifest| App
    SCHEDULED --> App
    App --> Stripe
    App --> SMTP
    Public --> Nginx
    Chrome -.->|Dusk| Nginx
```

Facts encoded here:

- The app container's WORKDIR is `/var/www/html/app` — never `cd app` inside it.
- Tests use the `bird_test` database on the same MySQL service; `make erd` also
  uses `bird_test` (fresh-migrated) so diagrams reflect the branch's migrations,
  not local dev-data drift.
- All scheduled work runs through Laravel's scheduler — there are no raw OS
  cron entries that bypass artisan.

## Staging / production

> **TODO(confirm with Akshay)** — to be drawn from real facts, not inferred.
> The checklist of needed details lives in
> [`prd/PRD-architecture-and-database-diagrams.md` §7](../../prd/PRD-architecture-and-database-diagrams.md)
> (hosting layout, queue driver & workers, scheduler trigger, storage driver,
> mail provider, MySQL hosting/backups, Stripe mode, monitoring).

Known production-relevant constraints already enforced in code:

- **Staging MySQL lacks the named-timezone tables** — `CONVERT_TZ` is unusable;
  every timezone conversion happens in PHP/Carbon (see TIMEZONE_GUIDE).
- Public surfaces that must be reachable without auth: Stripe webhook/checkout
  return URLs, invoice `payment_token` links, signed make-up response links.
