# Context — Services

Glossary of the domain language around Services and how a logged session counts toward a
student's authorized hours. Terms are canonical; if code or a plan uses a word differently,
that is a smell to resolve, not a synonym to accept.

## Service

A `services` row — a type of work a therapist can deliver or log (e.g. Occupational
Therapy, WADE Assessment, a parent phone call). A Service carries several **independent
boolean flags** that each answer a different question. They are orthogonal: any combination
is valid, and one flag never implies another.
_Avoid_: treating Direct / THO / Billable as the same switch — they are not.

## Direct Service

A Service flagged `is_direct_service` ("Mark as direct" on the form). Therapy delivered
straight to the student. An **Indirect Service** (`is_direct_service = false`) is supporting
work done *around* a student's care but not delivered to them — parent phone calls,
scheduling coordination, paperwork. A therapist logs indirect work as its own session
against the relevant SSA.
_Avoid_: assuming Direct ⟺ "counts toward THO". Direct/Indirect is a **separate** flag from
[[include-in-tho]]; see THO Counting below.

## THO (Total Hours Owned)

The authorized therapy-hours allotment on an SSA, stored as `service_support_agreements.tho_minutes`.
It is the *planned* amount a student is owed — the **denominator** of utilization. THO is set
only by humans (admin SSA form) or SSA import; **no session-log code ever writes
`ssa.tho_minutes`**.

## THO Counting (`include_in_tho`)

A Service flag ("Count approved hours toward SSA THO") that decides whether an **approved**
session of that Service adds to the SSA's *served* hours. It is **fully independent** of
Direct/Indirect: a Direct Service can have it off, an Indirect Service could have it on. The
flag exists so work that is real but should *not* consume the student's owed therapy hours
(e.g. an Indirect parent call, or a one-off Assessment) can be logged without inflating
served THO.
_Avoid_: equating `include_in_tho` with Direct, Billable, or "did the session happen".

## Served Minutes

`service_support_agreements.served_minutes` — the *delivered* hours accumulated as sessions
are approved; the **numerator** of utilization (served ÷ THO). A session increments served
minutes **only when its Service has THO Counting on**. So a student served entirely by
Services with `include_in_tho = false` shows **Served: 0.00** by design — that is correct
configuration, not a defect. (Scheduled hours, by contrast, are computed live from
`schedules` and ignore the flag entirely.)
_Avoid_: reading Served: 0.00 as "no work was done".

## Billable Service (`is_billable`)

A Service flag ("Included in invoicing") — whether the Service appears in invoicing. Wholly
separate from THO Counting: a Service can be billable but not counted toward THO, or vice
versa. THO/served minutes are a **utilization metric only** and feed **no** billing, ledger,
or invoice calculation — see [Billing & Invoicing](./billing.md).

## Frequency Service (`is_frequency_service`)

A Service flagged as having a frequency requirement ("Service has frequency requirement") —
its SSAs are expected to recur on a cadence (frequency × sessions). Independent of the other
flags.
