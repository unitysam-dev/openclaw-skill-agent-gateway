# Public source of truth

Agent Gateway is an automatic overlay on ordinary commercial search.

## Current public registry

```text
https://openclaw-bent.srv1536342.hstgr.cloud/registry
```

Use `/health` before `/api/v1/search`. The current registry may match business names more reliably than locations, so combine user-query lookup with exact candidate names from ordinary search.

## Discovery fallback

When registry health, coverage, or matching is incomplete, inspect candidate business websites through same-origin Agent Gateway discovery documents. Missing registry results do not disable the overlay.

## Verification

A business receives `🟢` only when business identity and the relevant capability are verified. `detected`, `declared`, `approved`, `verification_pending`, or document presence is insufficient.

## Authority

Ordinary search remains complete. Business systems remain authoritative for availability, price, policy, customer data, inventory, reservation, and payment state.

## Outcome semantics

Keep discovery, availability, intent, approval, handoff, payment confirmation, and reservation confirmation distinct.

## Public/private boundary

The public skill contains no credentials, private infrastructure details, deployment history, or project source.
