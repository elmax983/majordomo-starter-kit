# Roadmap

## Done (skeleton)

- Module page with package list, install and safe uninstall.
- State in `project_modules.DATA` (`installed`) with code hashes.
- Package `clock_chime`: `SK_Clock` / `StarterClock`, `onNewMinute/Hour/Day`.
- Package `sun_times`: `SK_Sun` / `StarterSun`, offline `date_sun_info()`.
- Module-local `lang/default.php` + `lang/ru.php` (no core dictionary edits).

## Next packages

1. **Basic entities** — virtual room, lamp, sensor, button, thermostat;
   properties, onchange methods and a small script.
2. **Scenes** — Good morning / Good night / Away / Home on virtual devices.
3. **Scripts** — welcome message, connection check, presence simulation,
   simple schedule; with comments and hints.
4. **Panel** — example home page blocks/widgets and a small layout.
5. **Hints** — per-package “what it shows” and “how to adapt” notes.

## Later

- Package versioning and safe upgrades.
- Track external references before uninstall.
- Optional integration with the `devices` module for virtual relays/sensors.
- Screenshots and a short demo video for the README.
- English/Russian docs; other languages after that.
