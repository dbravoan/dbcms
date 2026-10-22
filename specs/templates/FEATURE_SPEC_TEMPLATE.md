# Feature specification: [Name]

Status: draft | approved | implemented | superseded
Date: [YYYY-MM-DD]
Owner: [core subsystem, module or visual package]

## Objective and boundaries

Describe the user-visible behavior or simplification, the current problem and
what is outside the change. Reference the governing specification. For a retired
contract, identify its replacement and remove obsolete consumers/tests together.

## Behavior and affected contracts

Describe inputs, observable outputs and failure behavior. Include API, schema,
DTO or interface changes only when needed; otherwise state that the existing
contract remains. Keep types in their owner and avoid forwarding abstractions.

## Invariants

Record relevant authorization, publication, locale/market isolation, availability,
transaction and concurrency guarantees. State when remote effects occur. Public
SEO/discovery applies only to deliberately public content. Add migration or
recovery requirements only if this change affects them.

## Verification

List concrete scenarios, real entry points and the smallest fixtures that expose
failures. Select the fast, integration and release lanes from `docs/TESTING.md`.
Include compatibility/removal decisions and any unverified external assumptions.
Do not test line counts, incidental formatting or unused abstractions.
