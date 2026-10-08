# Command: /lts-check

Triggers the LTS Watchdog agent to:
1. Scan `composer.json` against Packagist/GitHub security advisories.
2. Check for new Laravel LTS minor/patch releases.
3. Propose safe upgrade commands and run regression test suite in Docker Sail.
