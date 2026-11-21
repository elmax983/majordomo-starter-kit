## Starter Kit v1.0.0

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
- Двуязычные README и гайд для начинающих со схемами интерфейса.

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
- Bilingual READMEs and a beginner guide with UI mockups.

**Package versions:** `basic_entities` 1, `clock_chime` 1, `sun_times` 1,
`modes` 1, `scenes` 2, `scripts` 2, `hints` 1, `panel` 2.

**Upgrade**

Install the module over the old copy (`install.sh`), open the module page and
press **Update selected**. Updates are additive and never overwrite edited
entities.

**Requirements:** MajorDoMo alpha, PHP 7.4/8.x, MariaDB/MySQL. No core or schema
changes.
