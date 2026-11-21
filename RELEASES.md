# Releases

Release notes for MajorDoMo Starter Kit. The same text can be used for a GitHub
release; the repository tag is `v1.0.0` for the first release.

## v1.0.0 — 2026-11-21

### Русский

Первый публичный релиз Starter Kit — необязательного модуля MajorDoMo, который
устанавливает готовые примеры в свежую систему.

**Что внутри**

- Восемь наборов: базовые сущности, режимы работы, сцены, скрипты, подсказки,
  панель, часы и солнце.
- Штатные сущности MajorDoMo: классы, объекты, свойства, методы, скрипты, сцены,
  `OperationalModes`, шаблоны классов, текстовые файлы, страницы фронтенда.
- Зависимости и порядок установки, защита от удаления зависимых наборов.
- Безопасное удаление: изменённые пользователем сущности остаются.
- Версии пакетов и аддитивные обновления.
- Локализация RU/EN без изменений словарей ядра.
- Статический валидатор пакетов и smoke-тест установщика.

**Версии пакетов:** `basic_entities` 1, `clock_chime` 1, `sun_times` 1,
`modes` 1, `scenes` 2, `scripts` 2, `hints` 1, `panel` 2.

**Обновление**

Установите модуль поверх старой копии (`install.sh`), откройте страницу модуля и
нажмите **«Обновить выбранные»**. Обновление добавляет новые сущности и не
перезаписывает изменённые.

**Требования:** MajorDoMo alpha, PHP 7.4/8.x, MariaDB/MySQL. Ядро и схема БД не
изменяются.

### English

First public release of Starter Kit, an optional MajorDoMo module that installs
ready-made examples into a fresh system.

**Highlights**

- Eight packages: basic entities, working modes, scenes, scripts, hints, panel,
  clock and sun.
- Regular MajorDoMo entities: classes, objects, properties, methods, scripts,
  scenes, `OperationalModes`, class templates, text files and frontend pages.
- Dependencies and install order, reverse-dependency protection.
- Safe uninstall: user-edited entities are kept.
- Package versions and additive updates.
- RU/EN localization without touching core dictionaries.
- Static package validator and installer smoke test.

**Package versions:** `basic_entities` 1, `clock_chime` 1, `sun_times` 1,
`modes` 1, `scenes` 2, `scripts` 2, `hints` 1, `panel` 2.

**Upgrade**

Install the module over the old copy (`install.sh`), open the module page and
press **Update selected**. Updates are additive and never overwrite edited
entities.

**Requirements:** MajorDoMo alpha, PHP 7.4/8.x, MariaDB/MySQL. No core or schema
changes.

## How to publish a release

1. Update package versions and `CHANGELOG.md`.
2. Run the checks:

   ```sh
   php tests/package_validate.php
   php tests/installer_smoke.php
   find modules tests -name '*.php' -print0 | xargs -0 -n1 php -l
   ```

3. Commit and tag:

   ```sh
   git tag -a v1.0.0 -m "Starter Kit v1.0.0"
   git push origin main
   git push origin v1.0.0
   ```

4. Push the tag. The GitHub Actions workflow `.github/workflows/release.yml`
   runs the checks and creates the release from `RELEASES.md` automatically:

   ```sh
   git push origin main
   git push origin v1.0.0
   ```

If the workflow is unavailable, create the release manually from the tag and
paste the matching section above.

## Versioning rules

- Module releases use semantic versions (`v1.0.0`).
- Package versions are integers and only grow.
- Adding entities/properties/methods → minor package bump.
- Changing or removing existing entities → requires an uninstall/install note in
  the release notes.
