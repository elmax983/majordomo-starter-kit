# Starter Kit

Optional MajorDoMo module that installs demo packages into a fresh system.

## Principles

- No core changes and no database schema changes.
- Module-local language files live in `lang/`; core dictionaries are not modified.
- Only regular MajorDoMo entities are created: classes, properties, objects,
  object methods and scripts.
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
ID plus a hash of generated code, so uninstall can detect manual edits.

## Package format

A package is a PHP file in `packages/` returning an array:

```php
return array(
    'id' => 'example',
    'version' => 1,
    'title_key' => 'LANG_KEY_TITLE',
    'description_key' => 'LANG_KEY_DESCRIPTION',
    'requires' => array('basic_entities'),   // optional, installed packages only
    'texts' => array(                        // optional {{TOKEN}} => LANG_KEY
        'ON' => 'STARTER_KIT_TEXT_ON'
    ),
    'classes' => array(
        array(
            'title' => 'SK_Example',
            'parent' => 'Timer',       // optional; needed for onNewMinute/onNewHour/onNewDay
            'description' => '...',
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

Classes are created before objects, objects before scripts, scenes next and frontend
pages last. List parent classes before their children.

`requires` lists package ids that must be installed first; the installer refuses
to install a package with missing dependencies and the module page shows them.

`{{TOKEN}}` placeholders are replaced at install time from the package `texts`
map (`TOKEN => LANG_KEY`), so generated entity titles, descriptions and code
contain plain text in the current interface language. Labels must not contain
single quotes because method code is single-quoted PHP.

Timed hooks (`onNewMinute`, `onNewHour`, `onNewDay`) are called by
`scripts/cycle_main.php` for objects of the `Timer` class and its subclasses.

Scene elements of type `button` run `action_object.action_method` on click
(`scenes` module: `ACTION_OBJECT` / `ACTION_METHOD`).

`layouts` entries create a frontend page (`TYPE=html`) and its cached file
`cms/layouts/<ID>.html`; the page appears as a tab on the main frontend. The
code can use the regular `%Object.Property|seconds%` placeholders for live
values. The file is removed together with the database row on uninstall.

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
7. A package cannot be uninstalled while another installed package requires it;
   uninstall the dependent packages first.
8. Module uninstall (from the module list) only removes the module registry
   entry; demo packages should be removed explicitly from the module page first.

## Current packages

- `clock_chime` — `SK_Clock` / `StarterClock`: current time, quiet hours,
  chime counter, optional `say()`.
- `sun_times` — `SK_Sun` / `StarterSun` + `SK_SunTimes` script: sunrise, sunset,
  civil/nautical/astronomical twilight and `dayPart` using PHP
  `date_sun_info()`.
- `basic_entities` — `SK_Device` base class and `SK_Lamp` / `SK_Sensor` /
  `SK_Button` / `SK_Thermostat`, `SK_Room`, demo objects and `SK_EntitiesDemo`.
- `scenes` — four scene pages (Good morning, Good night, Away, Home) driven by
  `SK_Scene` / `StarterScenes`; requires `basic_entities`.
- `scripts` — welcome message, offline health check, presence simulation and a
  two-slot schedule (`SK_Scripts`, `SK_Presence`, `SK_Schedule`); requires
  `basic_entities`.
- `panel` — a frontend tab with live cards for the lamp, sensor, thermostat,
  room, scenes and scripts; requires `basic_entities`, `scenes`, `scripts`.

`SK_Presence` and `SK_Schedule` are `Timer` subclasses, so their
`onNewMinute` / `onNewDay` methods start receiving calls after `cycle_main`
restarts (the module does not restart cycles on install).

## Tests

```sh
php tests/package_validate.php
```

The validator needs only PHP CLI: it checks the package format, method and
script syntax, `onchange` targets, parent classes, `{{TOKEN}}` replacements,
dependencies and the language dictionaries.
