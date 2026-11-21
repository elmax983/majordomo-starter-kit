# Changelog

All notable changes to MajorDoMo Starter Kit.

## 1.0.0 — 2026-11-21

First public release of the complete example set.

### Module

- Package list with install, update and safe uninstall.
- Dependency handling: `requires`, install order, reverse uninstall order and
  reverse-dependency protection.
- Package versions with installed/available display and additive updates.
- `{{TOKEN}}` localization at install time.
- Class display templates, text files, scenes and frontend layouts.
- Module-local dictionaries (`lang/default.php`, `lang/ru.php`).
- Static package validator and installer smoke test.

### Packages

| Package | Version | Contents |
|---|---|---|
| `basic_entities` | 1 | `SK_Device` → lamp / sensor / button / thermostat, `SK_Room`, demo objects, class templates, `SK_EntitiesDemo` |
| `clock_chime` | 1 | `SK_Clock`, quiet hours, chime counter, `onNewMinute/Hour/Day` |
| `sun_times` | 1 | `SK_Sun`, offline sunrise/sunset/twilight, `SK_SunTimes` |
| `modes` | 1 | `OperationalModes` Debug / Guests / Vacation, `StarterModes`, toggle scripts |
| `scenes` | 2 | Six scenes: morning, night, away, home, movie, guests |
| `scripts` | 2 | Welcome, health check, presence simulation, schedule, report |
| `hints` | 1 | Beginner guide text file, `StarterHints`, `SK_ShowHints` |
| `panel` | 2 | Frontend tab with live device, room, mode, scene, script and hint cards |

### Scenes 2

- Added Movie night and Guests.
- Guests activates the Guest mode.
- Requires `modes`.

### Scripts 2

- Added `SK_Report` writing `cms/texts/starter_kit_report.txt`.
- Requires `modes`.

### Panel 2

- Added modes and hints cards.
- Requires `modes` and `hints`.

## 0.1.0 — 2026-11-21

- Skeleton module, `clock_chime` and `sun_times` packages.
- Install/uninstall safety with code hashes.
