# Guía de Contribución a dbCMS / termoCMS

Bienvenido al repositorio oficial de **dbCMS** (y **termoCMS**). Para mantener la coherencia arquitectónica, robustez empresarial y compatibilidad con agentes autónomos de IA y desarrolladores humanos, seguimos estándares estrictos de Clean Architecture y Zero-Host Execution.

---

## 📖 Normas de Desarrollo Obligatorias

Antes de comenzar a desarrollar o proponer cambios, **es obligatorio leer la guía canónica**:
👉 **[Guía Canónica de Estándares, Convenciones y Clean Architecture (docs/DEVELOPER_CONVENTIONS_AND_STANDARDS.md)](docs/DEVELOPER_CONVENTIONS_AND_STANDARDS.md)**

---

## 🚀 Flujo de Trabajo Rápido (Zero-Host)

### 1. Requisito Fundamental: Docker Sail
**Nunca ejecutes `php`, `composer`, `artisan` o `pest` directamente en tu máquina anfitrión.**  
Utiliza siempre el script wrapper containerizado:

```bash
# Levantar el entorno Docker
./vendor/bin/sail up -d

# Ejecutar comandos Artisan
./bin/sail-run php artisan <comando>

# Ejecutar suite de pruebas
./bin/sail-run ./vendor/bin/pest
```

### 2. Ciclo de Desarrollo
1. **Spec-Driven Design (SDD)**: Define los contratos (`app/Contracts/`) y DTOs inmutables (`app/DTOs/`) antes de codificar la lógica.
2. **TDD (Test-Driven Development)**: Escribe las pruebas unitarias y funcionales en Pest PHP asegurando que fallen inicialmente.
3. **Thin Controllers & Fat Services**:
   - Controladores invocables de acción única (`*GetController`, `*PostController`, `*PutController`, `*DeleteController`), con **máximo 10 líneas** en `__invoke()`.
   - Lógica de negocio encapsulada en Servicios de Dominio con transacciones `DB::transaction()`.
   - Modelos Eloquent de esquema puro (sin algoritmos de negocio).
4. **Zero-Knowledge entre Módulos**: Los módulos núcleo nunca importan módulos opcionales. La extensión se realiza mediante View Composers, Observers dinámicos y eventos JS desacoplados (`cms-insert-block`).

---

## ✅ Criterios de Aceptación (Gating de Calidad)

Cualquier Pull Request o aportación debe superar con éxito:

```bash
# 1. Análisis estático riguroso (0 errores)
./bin/sail-run ./vendor/bin/phpstan --memory-limit=2G

# 2. Formato de código PSR-12 / Pint estricto
./bin/sail-run ./vendor/bin/pint --test

# 3. Suite completa de pruebas Pest (100% verde)
./bin/sail-run ./vendor/bin/pest
```
