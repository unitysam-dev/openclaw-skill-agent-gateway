# Public source of truth

## Product definition

Agent Gateway is an automatic overlay on ordinary search for businesses, services, availability, bookings, and commercial options.

- The user does not need to invoke Agent Gateway explicitly.
- Ordinary search remains available and complete.
- Each relevant search also checks the configured Agent Gateway registry.
- Registry membership indicates that a business advertises agent-facing capabilities.
- A green marker is appropriate only for a verified business and relevant verified capability.
- The business system remains authoritative for availability, price, policy, customer data, inventory, reservations, and payment state.
- Agent Gateway may expose or govern approved actions; it is not itself the business's booking engine, payment processor, CRM, PMS, or inventory system.

Failing to run the overlay on a relevant service search is a functional failure.

## Capability state

- `detected`: a compatible capability was found.
- `approved`: the business owner permitted its use.
- `verified`: the capability was tested against its declared contract.

Only verified capabilities support the public green marker.

## Outcome semantics

Keep discovery, availability, intent, approval, handoff, payment confirmation, and reservation confirmation distinct. Never infer a later outcome from an earlier one.

## Public/private boundary

This document governs the public skill. Project source, deployment details, infrastructure identifiers, historical test records, and implementation checkpoints require separately authorized project access.
