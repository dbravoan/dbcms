# Feature Specification: Real module mail and safe module packages

> **Spec ID**: `FEAT-NOTIFICATION-PACKAGE-HARDENING`  
> **Status**: IMPLEMENTED  
> **Author**: DomainEngineer  
> **Date**: 2026-10-12

## 1. Domain Objective & Context
Replace the notification email log stub with queued Laravel mail after commit. Deliver only to authorized, opted-in human recipients, with independent persisted delivery states; never redirect a private notification to another address. Install trusted code packages only after full archive validation and private staging, preserving existing modules and data on conflict or failure.

## 2. Algebraic Data Types & DTO Contracts
`ModulePackageDTO` is an immutable uploaded-file path and byte count. `InstalledModulePackageDTO` contains the installed directory and validated manifest. `ModulePackageInstallerInterface` installs and rolls back only the new package. `NotificationRecipientResolverInterface` resolves and revalidates current users and preferences.

## 3. Formal Invariants
- Mail work becomes visible to the queue only after commit. Rollback produces no delivery.
- Each recipient has a persisted pending/sending/sent/skipped/failed state; SMTP failures are observable and are not automatically retried after an uncertain delivery.
- User deletion, role/permission revocation, service identities and opt-outs prevent delivery. Email-only notifications do not appear in the in-app inbox.
- ZIP paths, entry types, duplicate paths, file/directory conflicts, encrypted entries, expansion size/count/ratio and manifest/provider ownership are validated before extraction.
- Staging and lock files are private; installing is an atomic rename under a process lock. Existing paths and module identifiers are never overwritten. Failed registration removes only the newly installed code.
- Uploaded PHP remains trusted administrator-supplied code, never a sandbox or an assurance of code safety.

## 4. REST & Authorization
Existing `POST /admin/modules/upload` remains guarded by `access:admin` and `manage:modules`. Invalid packages report validation errors, never success. Notification preference changes address the current session user only.

## 5. SEO
No public discovery changes; package staging and notification content remain private.

## 6. Test Invariant Checklist
- [x] RED/green archive security, conflicts, staging cleanup and valid installation.
- [x] RED/green real mail dispatch, targeted recipient isolation, preferences, authorization revalidation, failure status and transaction rollback.
- [x] Isolated local Mailpit delivery, Pint and PHPStan. See `docs/MODULE_NOTIFICATION_AND_PACKAGE_SECURITY.md` and private verification logs.
