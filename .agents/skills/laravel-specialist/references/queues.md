# Queue execution and recovery in dbCMS

Follow [Testing](../../../../docs/TESTING.md),
[deployment](../../../../docs/DEPLOYMENT_GUIDE.md) and the owning domain runbook.
Horizon is not installed. Workers, connection drivers and domain recovery are
separate concerns; a queue retry is not proof that repeating an external write is safe.

## Current configuration

`config/queue.php` defaults to the database connection. Database and Redis
`retry_after` default to 1500 seconds, exceeding the general 1200-second worker
timeout. The production Supervisor general worker uses `--tries=1`. Check actual
job overrides and the selected driver; the installer initially uses sync and
switches after migrations. Do not replace this with a generic 90-second sample.

Marketing owns `modules/Marketing/Config/marketing.php` and registers its
namespaced queue/cache/Redis configuration only with that composition. Its four
queues, flags and timeouts are documented in
[marketing operations](../../../../docs/MARKETING_MODULES_RUNBOOK.md).
Keep installation identity and APP_KEY stable; changing a namespace can strand
jobs rather than recover them.

## Dispatch and authority

For effects dependent on a successful local write, dispatch after commit. Jobs
carry owned IDs and trusted actor context, not credentials or actor IDs inferred
from model output. Revalidate current permissions, token/client, module and
resource availability when executing. Checkpoint durable intent before sending
a paid generation or external publication.

Use `modules/AiAssistant/Jobs/` and `app/Services/Orchestration/ExecutionService.php`
for task/execution examples. CreativeStudio records claim IDs, leases, stage
receipts and reservations; SocialPublishing records per-phase remote intent and
receipts. A unique queue job or cache lock alone cannot prove external idempotency.

## Diagnose and recover

After starting Sail, inspect only the selected development queue:

```bash
./vendor/bin/sail artisan queue:failed
```

Starting `queue:work`, including `--once`, consumes real queued work; run fixture
workers only in disposable tests. `queue:restart` signals long-lived workers to
exit after their current job; Supervisor or another manager must restart them.
Native VPS commands use its deployment wrapper rather than nested Sail.

Do not issue `queue:retry all`, flush receipts or delete locks as a generic fix.
Use the owner’s recovery operation. A `pending_remote` generation keeps its remote
ID; an `uncertain` execution and `pending_confirmation` social delivery require
evidence before another write. Cancellation prevents future stages and cannot
retract a remote mutation already accepted.

Verify rollback prevents dispatch, stale/revoked actors are denied, duplicate
claims do not duplicate effects and missing receipts remain uncertain. Mock the
remote boundary; retain separate transaction and real concurrency evidence.
