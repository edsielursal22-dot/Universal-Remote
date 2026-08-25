# Universal Remote

Universal Remote is a responsive PHP dashboard for controlling household devices from one simple workspace. It takes visual direction from the supplied dark, focused smart-remote reference while prioritizing readable hierarchy, large touch targets, strong feedback, and low cognitive load for users who may not be technical.

## What changed

The original project was a small participant-table scaffold with broken form wiring and a database model that already described a universal remote. The application now follows that intended product direction. It includes a device workspace, remote controls, app shortcuts, device search, activity history, settings, responsive mobile navigation, form validation, connection states, empty-state copy, loading feedback, toast feedback, reduced-motion support, and keyboard-friendly dialogs.

The remote actions are functional through `backend/api.php`. Commands are validated server-side and logged to `activity_logs`. Adding a device is validated on both the client and server, saved through prepared statements, and shown after refresh. Theme selection persists per device in `settings`.

## Architecture

The implementation uses a small separation of concerns:

| Layer | Responsibility |
| --- | --- |
| `index.php` | Server-rendered application shell, initial dashboard data, CSRF token, and accessible markup |
| `classes/RemoteRepository.php` | Database initialization, prepared queries, domain validation, device operations, commands, settings, and activity logging |
| `backend/api.php` | JSON request boundary with method checks, CSRF validation, input validation, and safe error responses |
| `assets/app.js` | Progressive enhancement for device switching, command requests, search, dialogs, toasts, and form behavior |
| `assets/app.css` | Responsive visual system, components, states, focus styling, and reduced-motion behavior |
| `database/schema.sql` | MySQL 8+ schema and seed data aligned to the supplied ERD |

No private credentials are committed. With no environment configuration, the app uses SQLite at `storage/remote.sqlite` and seeds a small demonstration workspace automatically. For MySQL, set `DB_DRIVER=mysql`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS` before serving the application.

## Run locally

Use PHP 8.1 or newer with PDO SQLite or PDO MySQL enabled:

```bash
php -S 127.0.0.1:8081 -t .
```

Then open `http://127.0.0.1:8081/index.php`. The first request creates the SQLite database and seeds three example devices. The `storage/` directory is runtime data and should be excluded from version control in a real deployment.

## Validation performed

The project was syntax-checked with PHP 8.3. The browser-rendered dashboard was exercised end to end: device switching, remote command submission, volume feedback, activity logging, live search, add-device validation and persistence, settings dialog opening, theme selection, and theme persistence. Responsive rules cover small phones, standard phones, and larger layouts; no external UI framework or remote font is required for the core experience.

## Remaining production integrations

The current backend is intentionally hardware-agnostic. It logs and acknowledges commands but does not yet communicate with a physical television, IR gateway, Bluetooth device, or vendor cloud API. A production adapter can be added behind `RemoteRepository::runCommand()` after the target hardware protocol and authentication model are selected. User login, password reset, and multi-account authorization are also not included because the supplied project contained no account service or identity requirements; the API is still protected against cross-site request forgery for the current session-based workflow.
