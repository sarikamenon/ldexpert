# E2E: Admin Audit Flow

**Flow:** Admin creates entity → Edits entity → Deactivates entity → Audit trail shows all events in order

**Dusk test:** `tests/BrowserQA/E2E/QaAdminAuditFlowBrowserTest.php`

## Steps

| # | Actor | Action | Verify |
|---|-------|--------|--------|
| 1 | Admin | Creates a new school | School in DB, audit row: `created` |
| 2 | Admin | Edits school display name | School updated, audit row: `updated` with old/new values |
| 3 | Admin | Deactivates school with reason | School status = INACTIVE, audit row: `updated` |
| 4 | Admin | Views audit trail for school | Three audit events in chronological order |
| 5 | Admin | Verifies audit content | Each row shows changed fields, actor, timestamp |

## Pass Criteria
- [ ] Three audit rows exist in correct order
- [ ] Each audit row shows correct `changed_from` / `changed_to`
- [ ] Actor shown as admin user name
- [ ] Timestamps in correct sequence

## Key DB Assertions
```php
$this->assertDatabaseHas('audits', [
    'auditable_type' => School::class,
    'event'          => 'updated',
]);
// Verify 3 total audit rows for this school
$this->assertCount(3, Audit::where('auditable_id', $school->id)->get());
```
