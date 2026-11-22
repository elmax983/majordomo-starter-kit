# Releases

Release notes for MajorDoMo Starter Kit.

## v0.1.2 — in development

The current development version. It is not published as a release yet: tags and
GitHub releases are created only after the example set is tested on a real
MajorDoMo stand.

### Русский

Текущая версия в разработке. Публичного релиза пока нет: тег и GitHub Release
создаются после проверки набора на реальном стенде MajorDoMo.

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
- Двуязычные README и гайд для начинающих; схемы интерфейса на русском и
  английском.

**Версии пакетов:** `basic_entities` 1, `clock_chime` 1, `sun_times` 1,
`modes` 1, `scenes` 2, `scripts` 2, `hints` 1, `panel` 2.

**Требования:** MajorDoMo alpha, PHP 7.4/8.x, MariaDB/MySQL. Ядро и схема БД не
изменяются.

### English

Current development version. There is no public release yet: the tag and GitHub
Release are created after testing on a real MajorDoMo stand.

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
- Bilingual READMEs and a beginner guide with Russian and English UI mockups.

**Package versions:** `basic_entities` 1, `clock_chime` 1, `sun_times` 1,
`modes` 1, `scenes` 2, `scripts` 2, `hints` 1, `panel` 2.

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
   git tag -a v0.1.2 -m "Starter Kit v0.1.2"
   git push origin main
   git push origin v0.1.2
   ```

4. The GitHub Actions workflow `.github/workflows/release.yml` runs the checks
   and creates the release from `docs/release-notes.md` automatically.

If the workflow is unavailable, create the release manually from the tag and
paste the matching section above.

## Versioning rules

- Module releases use semantic versions (`v0.1.2`, `v0.2.0`, `v1.0.0`).
- The module stays in `0.x` while examples and the package format are being
  polished.
- Package versions are integers and only grow.
- Adding entities/properties/methods → minor package bump.
- Changing or removing existing entities → requires an uninstall/install note in
  the release notes.
