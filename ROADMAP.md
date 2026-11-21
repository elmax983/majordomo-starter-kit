# Roadmap

## Done (skeleton)

- Module page with package list, install and safe uninstall.
- State in `project_modules.DATA` (`installed`) with code hashes.
- Package `clock_chime`: `SK_Clock` / `StarterClock`, `onNewMinute/Hour/Day`.
- Package `sun_times`: `SK_Sun` / `StarterSun`, offline `date_sun_info()`.
- Module-local `lang/default.php` + `lang/ru.php` (no core dictionary edits).
- Package `basic_entities`: `SK_Device` → `SK_Lamp` / `SK_Sensor` /
  `SK_Button` / `SK_Thermostat`, `SK_Room`, demo objects and `SK_EntitiesDemo`.
- `{{TOKEN}}` text substitution at install time (language-neutral packages).
- Static package validator: `php tests/package_validate.php`.
- Package `scenes`: Good morning / Good night / Away / Home, `SK_Scene` /
  `StarterScenes`, scene buttons via `ACTION_OBJECT` / `ACTION_METHOD`.
- `requires` dependencies between packages with a check in the installer and a
  note on the module page.
- Package `scripts`: `SK_Welcome`, `SK_ConnectionCheck`, presence simulation
  (`SK_Presence`), two-slot schedule (`SK_Schedule`), demo scripts.
- Package `panel`: frontend page (`layouts` + `cms/layouts/<ID>.html`) with
  live `%Object.Property|seconds%` cards.

## Next packages

1. **Hints** — per-package “what it shows” and “how to adapt” notes.

## Later

- Package versioning and safe upgrades.
- Track external references before uninstall.
- Optional integration with the `devices` module for virtual relays/sensors.
- Screenshots and a short demo video for the README.
- English/Russian docs; other languages after that.
