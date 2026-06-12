# Application Layers — the request flow every feature follows

Text contract with full rules: [`.claude/rules/ARCHITECTURE.md`](../../.claude/rules/ARCHITECTURE.md).

```mermaid
flowchart TD
    Browser([Browser])

    subgraph HTTP["HTTP layer"]
        Routes["Routes<br/>routes/{admin,therapist,student}.php<br/>middleware: auth + role + verified"]
        Controller["Controller<br/>app/Http/Controllers/{Role}/<br/>authorizes via Policy, no business logic"]
        FormRequest["FormRequest<br/>app/Http/Requests/{Role}/{Entity}/<br/>validates input, builds DTO"]
        Policy["Policy<br/>app/Policies/ (auto-discovered)"]
    end

    subgraph DOMAIN["Domain layer"]
        Service["Domain Service<br/>app/Domain/{Domain}/Services/<br/>business logic, transactions"]
        RepoIface["Repository Interface<br/>app/Domain/{Domain}/Repositories/"]
    end

    subgraph INFRA["Infrastructure layer"]
        Repo["Eloquent Repository<br/>app/Infrastructure/Repositories/<br/>bound in AppServiceProvider"]
        Model["Eloquent Model<br/>app/Models/<br/>relations, scopes, casts only"]
        DB[(MySQL)]
    end

    subgraph OUT["Response shaping"]
        View["Blade view<br/>pre-formatted strings only"]
        Resource["API Resource<br/>app/Http/Resources/ (JSON objects/lists)"]
        RowTransformer["RowTransformer<br/>app/DataTables/Transformers/ (DataTables rows)"]
    end

    subgraph SIDE["Side effects"]
        Mail["Mailables<br/>try/catch, never fail the primary action"]
        Audit["HasAudits / AuditRecorder<br/>audits table"]
        Ledger["LedgerService<br/>sole writer of ledger_entries"]
    end

    Browser --> Routes --> Controller
    Controller -.->|authorize| Policy
    Controller --> FormRequest -->|DTO| Service
    Service --> RepoIface --> Repo --> Model --> DB
    Service -.-> Mail
    Model -.->|updating / deleting| Audit
    Service -.->|finance writes| Ledger
    Controller --> View
    Controller --> Resource
    Controller --> RowTransformer
    View --> Browser
    Resource --> Browser
    RowTransformer --> Browser
```

Key rules the diagram encodes:

- **Controllers never query** — they authorize, delegate to a Service, and shape the response.
- **DTOs carry input** between FormRequest and Service; Services never read the HTTP request.
- **Repositories are the only query layer**; interfaces live in the domain, implementations in infrastructure.
- **Three response shapes**: Blade for pages, API Resources for JSON objects/lists, RowTransformers for DataTables rows (positional HTML arrays — never migrate these to Resources).
- **Side effects never 500 the primary action** — mail/notification failures are logged and swallowed (unless sending *is* the action).
- **`ledger_entries` has exactly one writer**: `LedgerService`.
