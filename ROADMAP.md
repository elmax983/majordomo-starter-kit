# Roadmap

## Done

- Module page with package list, install, update and safe uninstall.
- State in `project_modules.DATA` (`installed`) with code hashes and versions.
- Package `basic_entities`: `SK_Device` → `SK_Lamp` / `SK_Sensor` /
  `SK_Button` / `SK_Thermostat`, `SK_Room`, class templates, demo objects and
  `SK_EntitiesDemo`.
- Package `clock_chime`: `SK_Clock` / `StarterClock`, `onNewMinute/Hour/Day`.
- Package `sun_times`: `SK_Sun` / `StarterSun`, offline `date_sun_info()`.
- Package `modes`: `OperationalModes` Debug / Guests / Vacation,
  `StarterModes` hub, toggle scripts.
- Package `scenes`: six scenes (morning, night, away, home, movie, guests),
  `SK_Scene` / `StarterScenes`, buttons via `ACTION_OBJECT` / `ACTION_METHOD`.
- Package `scripts`: welcome, health check, presence simulation, two-slot
  schedule and report to a text file.
- Package `hints`: guide text file, `StarterHints`, `SK_ShowHints`, per-package
  “what it shows / how to adapt” notes on the module page.
- Package `panel`: frontend page (`layouts` + `cms/layouts/<ID>.html`) with live
  `%Object.Property|seconds%` cards for devices, modes, scenes, scripts and hints.
- `text_files` and class `template` support in the installer.
- `{{TOKEN}}` text substitution at install time (language-neutral packages).
- `requires` dependencies, dependency order and reverse-dependency protection.
- Package versioning: version display, update badge and additive updates.
- Bilingual docs (RU/EN), beginner guide and schematic UI mockups.
- Static package validator and installer smoke test.

## Next

1. **Real screenshots and a short demo video** for the README.
2. **Optional `devices` module integration** for virtual relays/sensors.
3. **Track external references before uninstall** (scenes, layouts, scripts).
4. **Package reset action** (uninstall + install) from the module page.
5. **More scenes/modes** (evening, maintenance, “nobody home” with presence).

## Later

- Other interface languages (module dictionaries only).
- Package signing / checksums for downloads.
- Import/export of package selections for fleets.
- Automated integration test on a disposable MajorDoMo stand.
