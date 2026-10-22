# Architecture and clean code

Follow [Core and extensions](../../specs/CORE_AND_EXTENSIONS.md) and
[conventions](../../docs/DEVELOPER_CONVENTIONS_AND_STANDARDS.md). Use strict PHP
types, PHPStan level 8 and Pint. Place useful DTOs/contracts in their owner;
concrete helpers need no mirror interface.
