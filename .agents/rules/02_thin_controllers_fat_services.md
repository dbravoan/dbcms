# Transport and domain boundaries

Follow [conventions](../../docs/DEVELOPER_CONVENTIONS_AND_STANDARDS.md).
Controllers validate, delegate and respond without persistence or business
algorithms. Do not split code into forwarding wrappers to meet a line quota.
The owning service coordinates atomic writes and after-commit remote effects.
