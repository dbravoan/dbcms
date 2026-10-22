# OpenCode instruction recipes

The JSON files in `agents/` describe repository roles; `commands/` contains human
readable task recipes. They are not a validated OpenCode client configuration
or executable dbCMS commands. Map the roles to the client version you actually
use rather than treating a `$schema` URL as proof of compatibility.

[AGENTS.md](../AGENTS.md), [Core and extensions](../specs/CORE_AND_EXTENSIONS.md)
and [TESTING](../docs/TESTING.md) are authoritative. Each role links its owning
skill or guide. The removed `config.json` declared an unimplemented orchestration
schema, fixed cache strategy and token quota; the repository had no loader for it.
No client import or remote model execution has been certified by editing these files.
