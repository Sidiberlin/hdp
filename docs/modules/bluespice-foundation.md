# Module: BlueSpiceFoundation

BlueSpiceFoundation (`app/extensions/BlueSpiceFoundation/`) is the core framework for the BlueSpice platform. It provides the service container, configuration management, extension registry, permission system, and common utilities that all other BlueSpice extensions depend on.

## Responsibilities

- **Service container** — DI wiring for shared BlueSpice services via MediaWiki's `ServiceWiring` mechanism
- **Configuration management** — `ConfigDefinition` registry, `bsg*` globals, Special:ConfigManager
- **Permission system** — Role-based access control with presets (`public`, `protected`, `private`)
- **Extension lifecycle** — Registration callbacks, hook handlers, run-jobs triggers
- **Maintenance tools** — ~25 maintenance scripts (user import/export, file processing, namespace recovery, etc.)
- **MWStake components** — Common UI components, content provisioner, run-jobs trigger framework

## Key Files

- [`extension.json`](../../app/extensions/BlueSpiceFoundation/extension.json) — Extension manifest with all hook registrations
- [`includes/ServiceWiring.php`](../../app/extensions/BlueSpiceFoundation/includes/ServiceWiring.php) — Service container definitions
- `src/` — Core PHP classes (services, config, permissions, utilities)
- `maintenance/` — ~25 maintenance scripts (BSImportUsers, BSMassEditLinks, BSMigrateSettings, etc.)
- `data/` — Static data files, Semantic MediaWiki config directory

## Permission System

BlueSpiceFoundation implements a role-based permission system configured via `$GLOBALS['bsgPermissionConfig']` and `$GLOBALS['bsgPermissionManagerActivePreset']`. Roles defined in `020-DefaultSettings.php`:

| Role Type | Group | Description |
|---|---|---|
| `implicit` | `*`, `user`, `autoconfirmed` | Automatic groups |
| `core-minimal` | `sysop` | Minimum admin |
| `core-extended` | `bureaucrat`, `bot`, `interface-admin`, `suppress` | Extended core |
| `extension-minimal` | `editor`, `reviewer` | Editorial roles |
| `extension-extended` | `autoreview`, `review`, `smw*`, `widgeteditor` | Extended roles |

Default preset: `private` (set in `030-BlueSpiceFree.php`).

## Dependencies

- **Uses:** MediaWiki core (services, hooks, config), MWStake components
- **Used by:** Every BlueSpice extension (loaded first in `030-BlueSpiceFree.php`)
- **Config dir:** `$IP/extensions/BlueSpiceFoundation/data/` (also used by SemanticMediaWiki for config storage)

## Notable Patterns / Gotchas

- **Must load first** — BlueSpiceFoundation is the first extension loaded (`030-BlueSpiceFree.php`, line 3). All other BlueSpice extensions depend on its services being available.
- **MWStake initialization** — `010-MWStakeComponents.php` calls `mwsInitComponents()` before any extension loads, ensuring the service container is ready for early `MediaWikiServices::getInstance()` calls.
- **Reserved usernames** — `020-DefaultSettings.php` defines ~15 reserved usernames (maintenance bots, default users) that are excluded from user stores.
- **Permission policy header** — Sets a default `Permissions-Policy` HTTP header via `BeforePageDisplay` hook, restricting browser features (camera, microphone, geolocation, etc.).
