# Boundary violations to avoid

- Querying Eloquent from Blade: prepare data in the owning reader/composer and
  preserve its authorization filters.
- Hardcoding optional module cases in generic core controllers: register owned
  settings, navigation, entity and capability contributions instead.
- Enabling a module because its directory exists or any boolean is named
  `is_enabled`: use `ModuleManager::isModuleEnabled()`, including requirements,
  installed/active state and the manifest's optional `availability_setting`.
- Creating interface/service/DTO chains that only forward the same call: retain
  abstractions only for meaningful boundaries or substitution.
- Retaining historical aliases solely to satisfy old tests: remove the retired
  contract and move relevant security/transaction coverage to its current owner.
- Exporting all entities to SEO/GEO: administrative entities are private by
  default; public contributors enforce publication, locale, market and availability.
- Running the entire Pest suite against working files: use the disposable
  integration/release lanes. A test database alone does not isolate `.env` or keys.

Follow [AGENTS.md](../../../../AGENTS.md) for Sail/bootstrap execution. Docker
runtime isolation does not protect host files that are mounted with write access.
