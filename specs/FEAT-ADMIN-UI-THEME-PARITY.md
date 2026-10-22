# Feature Specification: Controles administrativos coherentes en termoCMS y dbCMS

> **Spec ID**: `FEAT-ADMIN-UI-THEME-PARITY`  
> **Status**: APPROVED  
> **Author**: Superagent  

## 1. Domain Objective & Context

Unificar el diálogo de traducción y los controles compartidos del backoffice, manteniendo las paletas y tipografías de termoCMS y dbCMS. Revisar layouts, acceso, errores, dashboards, listados y formularios de ambos temas. Corregir estilos y estados desde componentes y tokens, con compatibilidad para campos existentes.

## 2. Algebraic Data Types & DTO Contracts

No cambia ningún DTO, dato de negocio o contrato de traducción. Inputs y selects conservan nombre, ID, valor, atributos Alpine y envío del formulario. El shell declara `data-admin-shell`; el CSS de compatibilidad se limita a esa raíz y excluye campos ocultos, técnicos y búsquedas internas de selectores compuestos.

## 3. Formal Invariants

- Los dos temas usan las mismas dimensiones y estados de controles, con sus propios colores y tipografía.
- Etiqueta, ayuda y validación se vinculan al campo correcto; foco y estados deshabilitados siguen siendo visibles.
- Preview, confirmaciones, polling y aplicación de traducción mantienen su comportamiento.
- Las reglas del backoffice no recolorean formularios públicos ni inputs internos de combobox.
- Móvil permite usar diálogo, navegación y notificaciones sin desbordamientos.

## 4. REST API & Passport OAuth2 Contract

Sin cambios de rutas, autenticación, permisos, proveedores ni confirmaciones. La revisión visual no genera contenido remoto ni guarda entidades.

## 5. Generative & Traditional SEO Specification

Sin cambios en SEO público, índices ni publicación. Los estilos administrativos permanecen aislados del frontoffice.

## 6. Test Invariant Checklist

- [x] Compilar vistas y assets en Sail; mantener las regresiones existentes de interfaz.
- [x] Renderizar ambos temas y los controles con errores, atributos Alpine y IDs explícitos.
- [x] Revisar visualmente traducción, formularios, listados y shells en los dos temas, con muestra móvil.
- [x] Documentar cobertura, cambios y limitaciones sin atribuir una auditoría visual a vistas no visitadas.
