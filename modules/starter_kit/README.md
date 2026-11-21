# Starter Kit

**English** | [Русский](README.ru.md)

Optional MajorDoMo module that installs demo packages into a fresh system.

## Principles

- No core changes and no database schema changes.
- Module-local language files live in `lang/`; core dictionaries are not modified.
- Only regular MajorDoMo entities are created: classes, properties, objects,
  methods, scripts, scenes, layouts and text files.
- Nothing is installed automatically: an administrator selects packages on the
  module page (`admin.php?action=starter_kit`).
- Only entities created by this module are removed, and only if they were not
  changed afterwards.
- No external APIs, no MQTT, no Telegram, no hardware.

## Language

The module ships its own language files in `modules/starter_kit/lang/`
(`default.php`, `ru.php`). The core `languages/*.php` dictionaries are not
modified; missing keys fall back to English.

## Installed state

The list of created entities is stored in `project_modules.DATA` for the
`starter_kit` module (serialized array, key `installed`). Each entity keeps its
ID plus a hash of generated code, so uninstall and updates can detect manual
edits. The record also stores the installed package `version`.

## Package format

A package is a PHP file in `packages/` returning an array:

```php
return array(
    'id' => 'example',
    'version' => 1,
    'title_key' => 'LANG_KEY_TITLE',
    'description_key' => 'LANG_KEY_DESCRIPTION',
    'requires' => array('basic_entities'),   // optional, installed packages only
    'hints' => array(                        // optional, shown on the module page
        'shows' => 'LANG_KEY_SHOWS',
        'adapt' => 'LANG_KEY_ADAPT'
    ),
    'texts' => array(                        // optional {{TOKEN}} => LANG_KEY
        'ON' => 'STARTER_KIT_TEXT_ON'
    ),
    'classes' => array(
        array(
            'title' => 'SK_Example',
            'parent' => 'Timer',       // optional; needed for onNewMinute/onNewHour/onNewDay
            'description' => '...',
            'template' => '<b>%.object_title%</b>: %.status%', // classes.TEMPLATE
            'properties' => array(
                'foo' => array(
                    'keep_history' => 0,
                    'onchange' => 'onFooChanged', // optional property onchange hook
                    'description' => '...'
                )
            ),
            'methods' => array('bar' => '/* PHP code without <?php */')
        )
    ),
    'objects' => array(
        array(
            'title' => 'StarterExample',
            'class' => 'SK_Example',
            'description' => '...',
            'properties' => array('foo' => 'default value'),
            'methods' => array('onNewMinute' => '...')
        )
    ),
    'text_files' => array(
        array('name' => 'starter_kit_example', 'content' => '...')
    ),
    'scripts' => array(
        array('title' => 'SK_ExampleScript', 'description' => '...', 'code' => '...')
    ),
    'scenes' => array(
        array(
            'title' => 'SK Example scene',
            'priority' => 10,
            'elements' => array(
                array(
                    'title' => 'Run example',
                    'type' => 'button',
                    'top' => 100, 'left' => 100, 'width' => 260, 'height' => 60,
                    'action_object' => 'StarterExample',
                    'action_method' => 'bar'
                )
            )
        )
    ),
    'layouts' => array(
        array(
            'title' => 'SK Example page',
            'type' => 'html',
            'priority' => 50,
            'code' => '<div>%StarterExample.status|10%</div>'
        )
    )
);
```

Creation order: classes, objects, text files, scripts, scenes, layouts. List
parent classes before their children.

`requires` lists package ids that must be installed first; the installer refuses
to install or update a package with missing dependencies and the module page
shows them. When several packages are selected at once, they are installed in
dependency order and uninstalled in reverse order.

`{{TOKEN}}` placeholders are replaced at install time from the package `texts`
map (`TOKEN => LANG_KEY`), so generated entity titles, descriptions, templates
and code contain plain text in the current interface language. Labels must not
contain single quotes because method code is single-quoted PHP.

Timed hooks (`onNewMinute`, `onNewHour`, `onNewDay`) are called by
`scripts/cycle_main.php` for objects of the `Timer` class and its subclasses.

Scene elements of type `button` run `action_object.action_method` on click
(`scenes` module: `ACTION_OBJECT` / `ACTION_METHOD`).

`layouts` entries create a frontend page (`TYPE=html`) and its cached file
`cms/layouts/<ID>.html`; the page appears as a tab on the main frontend. The
code can use the regular `%Object.Property|seconds%` placeholders for live
values. The file is removed together with the database row on uninstall.

`text_files` entries create `cms/texts/<name>.txt`, visible in
**Control panel → Text files** and readable by scripts with `LoadFile()`.

`classes[].template` is written to the standard `classes.TEMPLATE` field and
uses the regular `%.property%` placeholders (`%.object_title%` is substituted
automatically).

## Versions and updates

Package `version` is an integer. The module page shows `v<available>` next to the
title and an update badge when the installed version is lower. The **Update
selected** button runs an additive update:

1. new classes, objects, text files, scripts, scenes and layouts are created;
2. missing properties and methods are added to classes and objects created by
   this module;
3. scripts, layouts and text files are updated only if their current content
   still matches the stored hash;
4. existing scenes are never rewritten.

User-edited entities are skipped and reported. To fully reset a package, uninstall
it and install it again.

## Safety rules

1. Install aborts before creating anything if an entity with the same title
   already exists.
2. Re-installing an installed package is a no-op.
3. Uninstall skips entities that were renamed, extended with new properties or
   methods, or whose generated code was edited.
4. Classes still used by objects or subclasses are never deleted.
5. Scenes are removed only when their titles, element sets, button labels and
   actions are unchanged.
6. Frontend pages are removed only when the database row and the cached
   `cms/layouts/<ID>.html` file are unchanged; manually edited pages are kept.
7. Text files are removed only when their content is unchanged.
8. A package cannot be uninstalled while another installed package requires it;
   uninstall the dependent packages first.
9. Module uninstall (from the module list) only removes the module registry
   entry; demo packages should be removed explicitly from the module page first.

## Current packages

- `clock_chime` v1 — `SK_Clock` / `StarterClock`: current time, quiet hours,
  chime counter, optional `say()`.
- `sun_times` v1 — `SK_Sun` / `StarterSun` + `SK_SunTimes` script: sunrise,
  sunset, civil/nautical/astronomical twilight and `dayPart` using PHP
  `date_sun_info()`.
- `basic_entities` v1 — `SK_Device` base class and `SK_Lamp` / `SK_Sensor` /
  `SK_Button` / `SK_Thermostat`, `SK_Room`, class templates, demo objects and
  `SK_EntitiesDemo`.
- `modes` v1 — three `OperationalModes` objects (Debug, Guests, Vacation),
  `SK_Modes` / `StarterModes` summary hub and toggle scripts; requires
  `basic_entities`.
- `scenes` v2 — six scenes (Good morning, Good night, Away, Home, Movie night,
  Guests) driven by `SK_Scene` / `StarterScenes`; requires `basic_entities` and
  `modes`.
- `scripts` v2 — welcome message, offline health check, presence simulation,
  two-slot schedule, report to a text file (`SK_Scripts`, `SK_Presence`,
  `SK_Schedule`); requires `basic_entities` and `modes`.
- `hints` v1 — `starter_kit_hints.txt`, `SK_Hints` / `StarterHints` and
  `SK_ShowHints`; requires `basic_entities`.
- `panel` v2 — a frontend tab with live cards for the lamp, sensor, thermostat,
  room, modes, scenes, scripts and hints; requires `basic_entities`, `modes`,
  `scenes`, `scripts` and `hints`.

`SK_Presence` and `SK_Schedule` are `Timer` subclasses, so their
`onNewMinute` / `onNewDay` methods start receiving calls after `cycle_main`
restarts (the module does not restart cycles on install).

## Tests

```sh
php tests/package_validate.php
php tests/installer_smoke.php
```

The validator needs only PHP CLI: it checks the package format, method and
script syntax, `onchange` targets, parent classes, `{{TOKEN}}` replacements,
dependencies, hints, scene actions, layout placeholders and text file names.

The smoke test stubs SQL and `ROOT` to exercise scene, layout and text file
creation, safe removal and additive package updates (unchanged entities are
updated or removed, edited ones are kept). It does not check the real database
schema: run a full install on a test stand before release.
