# Passport authorization

Follow [Passport](../../docs/07_PASSPORT_OAUTH2_SPECIFICATION.md) and the canonical
capability authorizer. Preserve both user permissions and actual token scopes;
a scope alone does not grant the service account authority. Derive actor identity
from authentication, not request/model claims. Keep private keys and tokens out
of logs and repository changes.
