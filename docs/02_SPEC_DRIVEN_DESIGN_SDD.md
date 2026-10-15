# Document 02: Spec-Driven Design (SDD) & Formal Contracts

## Theoretical Overview
Spec-Driven Design (SDD) adapts formal software methods (Bjorner, 2006; Meyer, 1992) to agentic workflows. Instead of directly instructing an LLM to generate production code from an ambiguous natural language prompt, the engineering process is partitioned into formal phases:

1. **Specification Synthesis ($S$)**: The `SpecArchitect` creates a formal mathematical contract containing preconditions, invariants, postconditions, and typed DTO definitions.
2. **Verification & Testing ($V$)**: The `TddCraftsman` translates the specification into deterministic assertions (`Pest` tests).
3. **Domain Implementation ($I$)**: The `DomainEngineer` implements the minimal code required to satisfy $V \models S$.

```
[User Goal / RFC]
       |
       v
[SpecArchitect] --------> specs/{feature}.md + DTOs
                               |
            +------------------+------------------+
            |                                     |
            v                                     v
     [TddCraftsman]                       [DomainEngineer]
            |                                     |
   tests/Unit & Feature                   app/Services/*
            |                                     |
            +------------------+------------------+
                               |
                               v
               ./vendor/bin/sail pest (100% Green)
```

## Mathematical Formalism of Hoare Triples in SDD
For any domain action $A$, we define the triple $\{P\} A \{R\}$:
- **$P$ (Precondition)**: The state requirement prior to execution (e.g., input DTO validation, authorized OAuth2 scope).
- **$A$ (Action)**: The transactional mutation inside `app/Services/`.
- **$R$ (Postcondition)**: The guaranteed state after execution (e.g., database record created, domain event dispatched, response DTO returned).

## How specifications reduce ambiguity
- **Bounded Solution Space**: An LLM provided with concrete DTO types and explicit Hoare triples does not have to invent property names or guess return structures.
- **Fail-Fast Boundary**: If an agent generates invalid logic, Pest unit tests fail instantly during the deterministic CI loop, providing a regression gate for the cases actually covered. Passing tests does not prove all possible behaviours or production conditions.
