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
    'classes' => array(
        array(
            'title' => 'SK_Example',
            'parent' => 'Timer',       // optional; needed for onNewMinute/onNewHour/onNewDay
            'description' => '...',
            'properties' => array('foo' => array('keep_history' => 0)),
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
    )
);
```

Timed hooks (`onNewMinute`, `onNewHour`, `onNewDay`) are called by
`scripts/cycle_main.php` for objects of the `Timer` class and its subclasses.

## Safety rules

1. Install aborts before creating anything if an entity with the same title
   already exists.
2. Re-installing an installed package is a no-op.
3. Uninstall skips entities that were renamed, extended with new properties or
   methods, or whose generated code was edited.
4. Classes still used by objects or subclasses are never deleted.
5. Module uninstall (from the module list) only removes the module registry
   entry; demo packages should be removed explicitly from the module page first.

## Current packages

- `clock_chime` — `SK_Clock` / `StarterClock`: current time, quiet hours,
  chime counter, optional `say()`.
- `sun_times` — `SK_Sun` / `StarterSun` + `SK_SunTimes` script: sunrise, sunset,
  civil/nautical/astronomical twilight and `dayPart` using PHP
  `date_sun_info()`.
