# Feature Specification: [Feature Name]

> **Spec ID**: `FEAT-[NUMBER]`  
> **Status**: DRAFT | APPROVED | IMPLEMENTED  
> **Author**: `SpecArchitect` Agent  
> **Date**: [YYYY-MM-DD]

---

## 1. Domain Objective & Context
*Concise explanation of the business problem and domain boundaries.*

## 2. Algebraic Data Types & DTO Contracts
```php
namespace App\DTOs\[Domain];

readonly class [Feature]DTO
{
    public function __construct(
        public string $fieldA,
        public int $fieldB,
        public array $options = []
    ) {}
}
```

## 3. Formal Invariants (Hoare Logic: $P \{Q\} R$)
- **Preconditions ($P$)**:
  - Request input satisfies `[Feature]Request` validation rules.
  - User has Passport OAuth2 scope: `[scope:name]`.
- **State Mutations / Processing ($Q$)**:
  - Encapsulated inside `DB::transaction()`.
  - Dispatches `[Feature]CompletedEvent`.
- **Postconditions ($R$)**:
  - Record persisted in database with valid foreign key constraints.
  - Cached aggregate updated in Redis.

## 4. REST API & Passport OAuth2 Contract
- **Endpoint**: `POST /api/v1/[resource]`
- **Headers**: `Authorization: Bearer <token>`, `Accept: application/json`
- **Required OAuth Scopes**: `['read:domain', 'write:domain']`
- **Success Response**: `201 Created`
- **Failure Responses**: `401 Unauthorized`, `422 Unprocessable Entity`, `409 Conflict`

## 5. Generative & Traditional SEO Specification
- **JSON-LD Schema Type**: `SchemaOrgType::Article` or `SchemaOrgType::TechArticle`
- **llms.txt Summary**: Added to dynamic `/llms.txt` under `## [Feature Category]`.

## 6. Test Invariant Checklist (TDD)
- [ ] Unit Test: Service executes within `DB::transaction`.
- [ ] Feature Test: Endpoint rejects unauthenticated requests with `401`.
- [ ] Feature Test: Endpoint rejects token missing required scope with `403`.
- [ ] Arch Test: Controller contains $\le 5$ lines of code.
