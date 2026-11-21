<?php
/**
 * Starter Kit
 *
 * Optional module installing ready-to-use demo packages: classes, objects,
 * properties, methods and scripts. It never changes the core schema and only
 * removes entities it created itself.
 *
 * @package MajorDoMo
 * @version 0.1
 */

class starter_kit extends module
{
    /**
     * @var array package definitions loaded from packages/*.php
     */
    var $packages = array();

    /**
     * @var string current POST mode (install|uninstall)
     */
    var $mode;

    /**
     * @var array selected package ids
     */
    var $selected = array();

    /**
     * @var array {{TOKEN}} => LANG_KEY map of the package being installed
     */
    var $texts = array();

    function __construct()
    {
        $this->name = 'starter_kit';
        $this->version = '1.0.0';
        $this->loadLanguage();
        $this->title = '<#LANG_STARTER_KIT_TITLE#>';
        $this->module_category = '<#LANG_SECTION_SYSTEM#>';
        $this->checkInstalled();
        $this->loadPackages();
        $this->normalizeConfig();
    }

    /**
     * Load module-local language constants (no core dictionary changes).
     */
    function loadLanguage()
    {
        $lang = defined('SETTINGS_SITE_LANGUAGE') ? SETTINGS_SITE_LANGUAGE : 'default';
        $files = array(
            dirname(__FILE__) . '/lang/' . $lang . '.php',
            dirname(__FILE__) . '/lang/default.php',
        );
        foreach ($files as $file) {
            if (!file_exists($file)) {
                continue;
            }
            $dictionary = include($file);
            if (!is_array($dictionary)) {
                continue;
            }
            foreach ($dictionary as $key => $value) {
                if (!defined('LANG_' . $key)) {
                    define('LANG_' . $key, $value);
                }
            }
        }
    }

    function loadPackages()
    {
        $this->packages = array();
        $files = glob(dirname(__FILE__) . '/packages/*.php');
        if (!is_array($files)) {
            return;
        }
        sort($files);
        foreach ($files as $file) {
            $package = include($file);
            if (!is_array($package) || !isset($package['id'])) {
                continue;
            }
            $this->packages[$package['id']] = $package;
        }
    }

    function normalizeConfig()
    {
        $this->getConfig();
        if (!is_array($this->config)) {
            $this->config = array();
        }
        if (!isset($this->config['installed']) || !is_array($this->config['installed'])) {
            $this->config['installed'] = array();
        }
    }

    /**
     * Resolve a language key without requiring it to be defined.
     */
    function lang($key, $fallback = '')
    {
        $constant = 'LANG_' . $key;
        if (defined($constant)) {
            return constant($constant);
        }
        return $fallback != '' ? $fallback : $key;
    }

    /**
     * Replace {{TOKEN}} placeholders with module-local translations.
     *
     * Packages keep their generated content language-neutral and declare a
     * "texts" map: TOKEN => LANG_KEY. The replacement happens at install time,
     * so the created entities contain plain text in the current language.
     * Labels must not contain single quotes (method code is single-quoted PHP).
     */
    function resolveText($text)
    {
        if (!is_string($text) || strpos($text, '{{') === false) {
            return $text;
        }
        $replace = array();
        foreach ($this->texts as $token => $key) {
            $replace['{{' . $token . '}}'] = $this->lang($key, $token);
        }
        if (!count($replace)) {
            return $text;
        }
        return str_replace(array_keys($replace), array_values($replace), $text);
    }

    function run()
    {
        $out = array();
        $this->admin($out);
        $out['ACTION'] = $this->action;
        $out['MODE'] = $this->mode;
        $out['MODULE_VERSION'] = $this->version;
        $this->data = $out;
        $p = new parser(DIR_TEMPLATES . $this->name . '/' . $this->name . '.html', $this->data, $this);
        $this->result = $p->result;
    }

    function admin(&$out)
    {
        $messages = array();

        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST') {
            $mode = gr('mode');
            $selected = isset($_POST['pkg']) ? (array)$_POST['pkg'] : array();
            $selected = array_values(array_intersect(array_map('strval', $selected), array_keys($this->packages)));

            if ($mode != '' && !count($selected)) {
                $messages[] = array(
                    'CLASS' => 'warning',
                    'TEXT' => $this->lang('STARTER_KIT_NOTHING_SELECTED')
                );
            }

            if ($mode == 'install' || $mode == 'update') {
                // Install/update dependencies first, even if the user selected
                // the packages in a different order.
                $selected = $this->sortByRequirements($selected);
            } elseif ($mode == 'uninstall') {
                // Remove dependent packages before their dependencies.
                $selected = array_reverse($this->sortByRequirements($selected));
            }

            foreach ($selected as $package_id) {
                if ($mode == 'install') {
                    $result = $this->installPackage($package_id);
                } elseif ($mode == 'update') {
                    $result = $this->updatePackage($package_id);
                } elseif ($mode == 'uninstall') {
                    $result = $this->uninstallPackage($package_id);
                } else {
                    $result = array('ok' => false, 'messages' => array());
                }
                foreach ($result['messages'] as $message) {
                    $messages[] = $message;
                }
            }
            $this->saveConfig();
        }

        $out['MESSAGES'] = $messages;
        $out['PACKAGES'] = $this->getPackagesForView();
    }

    function getPackagesForView()
    {
        $result = array();
        foreach ($this->packages as $id => $package) {
            $this->texts = isset($package['texts']) && is_array($package['texts']) ? $package['texts'] : array();
            $installed = isset($this->config['installed'][$id]);
            $record = $installed ? $this->config['installed'][$id] : array();
            $conflicts = $installed ? array() : $this->detectConflicts($package);
            $note = '';
            if (count($conflicts)) {
                $note = $this->lang('STARTER_KIT_CONFLICT') . ': ' . implode(', ', $conflicts);
            }

            $requires = array();
            $requires_missing = array();
            if (isset($package['requires']) && is_array($package['requires'])) {
                foreach ($package['requires'] as $required_id) {
                    $required_title = isset($this->packages[$required_id])
                        ? $this->lang($this->packages[$required_id]['title_key'], $required_id)
                        : $required_id;
                    $requires[] = $required_title;
                    if (!isset($this->config['installed'][$required_id])) {
                        $requires_missing[] = $required_title;
                    }
                }
            }

            $required_by = array();
            foreach ($this->packages as $other_id => $other_package) {
                if ($other_id == $id || !isset($this->config['installed'][$other_id])) {
                    continue;
                }
                if (isset($other_package['requires']) && in_array($id, $other_package['requires'], true)) {
                    $required_by[] = $this->lang($other_package['title_key'], $other_id);
                }
            }

            $hints = isset($package['hints']) && is_array($package['hints']) ? $package['hints'] : array();
            $hint_shows = isset($hints['shows']) ? $this->lang($hints['shows'], '') : '';
            $hint_adapt = isset($hints['adapt']) ? $this->lang($hints['adapt'], '') : '';
            $package_version = isset($package['version']) ? (int)$package['version'] : 1;
            $installed_version = isset($record['version']) ? (int)$record['version'] : 0;

            $result[] = array(
                'ID' => $id,
                'TITLE' => $this->lang($package['title_key'], $id),
                'DESCRIPTION' => $this->lang($package['description_key'], ''),
                'INSTALLED' => $installed ? 1 : 0,
                'INSTALLED_DATE' => isset($record['installed_at']) ? $record['installed_at'] : '',
                'NOTE' => $note,
                'REQUIRES' => implode(', ', $requires),
                'REQUIRES_MISSING' => implode(', ', $requires_missing),
                'REQUIRED_BY' => implode(', ', $required_by),
                'SHOW_HINT' => $hint_shows,
                'ADAPT_HINT' => $hint_adapt,
                'VERSION' => $package_version,
                'INSTALLED_VERSION' => $installed_version,
                'UPDATE_AVAILABLE' => ($installed && $installed_version < $package_version) ? 1 : 0,
            );
        }
        $this->texts = array();
        return $result;
    }

    /**
     * Check whether package entity titles are already used by something that
     * this module did not create.
     */
    function detectConflicts($package)
    {
        $conflicts = array();
        foreach (array('classes', 'objects') as $type) {
            if (!isset($package[$type]) || !is_array($package[$type])) {
                continue;
            }
            foreach ($package[$type] as $entity) {
                $table = $type == 'classes' ? 'classes' : 'objects';
                $rec = SQLSelectOne("SELECT ID FROM " . $table . " WHERE TITLE='" . DBSafe($this->resolveText($entity['title'])) . "'");
                if ($rec['ID']) {
                    $conflicts[] = $this->resolveText($entity['title']);
                }
            }
        }
        if (isset($package['scripts']) && is_array($package['scripts'])) {
            foreach ($package['scripts'] as $entity) {
                $rec = SQLSelectOne("SELECT ID FROM scripts WHERE TITLE='" . DBSafe($this->resolveText($entity['title'])) . "'");
                if ($rec['ID']) {
                    $conflicts[] = $this->resolveText($entity['title']);
                }
            }
        }
        if (isset($package['scenes']) && is_array($package['scenes'])) {
            foreach ($package['scenes'] as $entity) {
                $title = $this->resolveText($entity['title']);
                $rec = SQLSelectOne("SELECT ID FROM scenes WHERE TITLE='" . DBSafe($title) . "'");
                if ($rec['ID']) {
                    $conflicts[] = $title;
                }
            }
        }
        if (isset($package['layouts']) && is_array($package['layouts'])) {
            foreach ($package['layouts'] as $entity) {
                $title = $this->resolveText($entity['title']);
                $rec = SQLSelectOne("SELECT ID FROM layouts WHERE TITLE='" . DBSafe($title) . "'");
                if ($rec['ID']) {
                    $conflicts[] = $title;
                }
            }
        }
        if (isset($package['text_files']) && is_array($package['text_files'])) {
            foreach ($package['text_files'] as $entity) {
                $name = isset($entity['name']) ? $entity['name'] : '';
                if ($name == '' || !defined('ROOT')) {
                    continue;
                }
                if (file_exists(ROOT . 'cms/texts/' . $name . '.txt')) {
                    $conflicts[] = 'cms/texts/' . $name . '.txt';
                }
            }
        }
        return $conflicts;
    }

    /**
     * Order selected packages so that dependencies are installed first.
     * Packages that are not selected are not added: a missing dependency is
     * reported by installPackage().
     */
    function sortByRequirements($ids)
    {
        $sorted = array();
        $visiting = array();
        $visit = function ($id) use (&$visit, &$sorted, &$visiting, &$ids) {
            if (isset($sorted[$id]) || isset($visiting[$id])) {
                return;
            }
            $visiting[$id] = 1;
            if (isset($this->packages[$id]['requires']) && is_array($this->packages[$id]['requires'])) {
                foreach ($this->packages[$id]['requires'] as $required_id) {
                    if (in_array($required_id, $ids, true)) {
                        $visit($required_id);
                    }
                }
            }
            unset($visiting[$id]);
            $sorted[$id] = 1;
        };
        foreach ($ids as $id) {
            $visit($id);
        }
        return array_keys($sorted);
    }

    function installPackage($package_id)
    {
        $result = array('ok' => false, 'messages' => array());
        if (!isset($this->packages[$package_id])) {
            $result['messages'][] = array('CLASS' => 'danger', 'TEXT' => 'Unknown package: ' . $package_id);
            return $result;
        }
        if (isset($this->config['installed'][$package_id])) {
            $result['ok'] = true;
            $result['messages'][] = array(
                'CLASS' => 'info',
                'TEXT' => $this->lang($this->packages[$package_id]['title_key'], $package_id) . ': ' . $this->lang('STARTER_KIT_ALREADY_INSTALLED')
            );
            return $result;
        }

        $package = $this->packages[$package_id];
        $this->texts = isset($package['texts']) && is_array($package['texts']) ? $package['texts'] : array();

        $requires_missing = array();
        if (isset($package['requires']) && is_array($package['requires'])) {
            foreach ($package['requires'] as $required_id) {
                if (!isset($this->config['installed'][$required_id])) {
                    $requires_missing[] = isset($this->packages[$required_id])
                        ? $this->lang($this->packages[$required_id]['title_key'], $required_id)
                        : $required_id;
                }
            }
        }
        if (count($requires_missing)) {
            $result['messages'][] = array(
                'CLASS' => 'danger',
                'TEXT' => $this->lang($package['title_key'], $package_id) . ': ' .
                    $this->lang('STARTER_KIT_REQUIRES') . ' ' . implode(', ', $requires_missing) .
                    ' (' . $this->lang('STARTER_KIT_REQUIRES_MISSING') . ')'
            );
            return $result;
        }

        $conflicts = $this->detectConflicts($package);
        if (count($conflicts)) {
            $result['messages'][] = array(
                'CLASS' => 'danger',
                'TEXT' => $this->lang($package['title_key'], $package_id) . ': ' .
                    $this->lang('STARTER_KIT_CONFLICT') . ' (' . implode(', ', $conflicts) . ')'
            );
            return $result;
        }

        $record = array(
            'version' => isset($package['version']) ? (int)$package['version'] : 1,
            'installed_at' => date('Y-m-d H:i:s'),
            'entities' => array(
                'classes' => array(),
                'objects' => array(),
                'text_files' => array(),
                'scripts' => array(),
                'scenes' => array(),
                'layouts' => array()
            )
        );

        // Classes first: objects may depend on them.
        if (isset($package['classes']) && is_array($package['classes'])) {
            foreach ($package['classes'] as $entity) {
                $class_record = $this->createClass($entity);
                if ($class_record === false) {
                    $result['messages'][] = array(
                        'CLASS' => 'danger',
                        'TEXT' => $this->lang($package['title_key'], $package_id) . ': cannot create class ' . $entity['title']
                    );
                    $this->rollbackRecord($record);
                    return $result;
                }
                $record['entities']['classes'][$entity['title']] = $class_record;
            }
        }

        if (isset($package['objects']) && is_array($package['objects'])) {
            foreach ($package['objects'] as $entity) {
                $object_record = $this->createObject($entity);
                if ($object_record === false) {
                    $result['messages'][] = array(
                        'CLASS' => 'danger',
                        'TEXT' => $this->lang($package['title_key'], $package_id) . ': cannot create object ' . $entity['title']
                    );
                    $this->rollbackRecord($record);
                    return $result;
                }
                $record['entities']['objects'][$entity['title']] = $object_record;
            }
        }

        if (isset($package['text_files']) && is_array($package['text_files'])) {
            foreach ($package['text_files'] as $entity) {
                $text_record = $this->createTextFile($entity);
                if ($text_record === false) {
                    $result['messages'][] = array(
                        'CLASS' => 'danger',
                        'TEXT' => $this->lang($package['title_key'], $package_id) . ': cannot create text file ' . $entity['name']
                    );
                    $this->rollbackRecord($record);
                    return $result;
                }
                $record['entities']['text_files'][$text_record['name']] = $text_record;
            }
        }

        if (isset($package['scripts']) && is_array($package['scripts'])) {
            foreach ($package['scripts'] as $entity) {
                $script_record = $this->createScript($entity);
                if ($script_record === false) {
                    $result['messages'][] = array(
                        'CLASS' => 'danger',
                        'TEXT' => $this->lang($package['title_key'], $package_id) . ': cannot create script ' . $entity['title']
                    );
                    $this->rollbackRecord($record);
                    return $result;
                }
                $record['entities']['scripts'][$entity['title']] = $script_record;
            }
        }

        // Scenes come last: their elements may call methods of the objects above.
        if (isset($package['scenes']) && is_array($package['scenes'])) {
            foreach ($package['scenes'] as $entity) {
                $scene_record = $this->createScene($entity);
                if ($scene_record === false) {
                    $result['messages'][] = array(
                        'CLASS' => 'danger',
                        'TEXT' => $this->lang($package['title_key'], $package_id) . ': cannot create scene ' . $this->resolveText($entity['title'])
                    );
                    $this->rollbackRecord($record);
                    return $result;
                }
                $record['entities']['scenes'][$scene_record['title']] = $scene_record;
            }
        }

        // Frontend pages (layouts) are last: they only display the entities above.
        if (isset($package['layouts']) && is_array($package['layouts'])) {
            foreach ($package['layouts'] as $entity) {
                $layout_record = $this->createLayout($entity);
                if ($layout_record === false) {
                    $result['messages'][] = array(
                        'CLASS' => 'danger',
                        'TEXT' => $this->lang($package['title_key'], $package_id) . ': cannot create layout ' . $this->resolveText($entity['title'])
                    );
                    $this->rollbackRecord($record);
                    return $result;
                }
                $record['entities']['layouts'][$layout_record['title']] = $layout_record;
            }
        }

        $this->config['installed'][$package_id] = $record;
        $result['ok'] = true;
        $result['messages'][] = array(
            'CLASS' => 'success',
            'TEXT' => $this->lang($package['title_key'], $package_id) . ': ' . $this->lang('STARTER_KIT_INSTALL_DONE')
        );
        return $result;
    }

    /**
     * Check package requirements against the installed packages.
     */
    function getMissingRequirements($package)
    {
        $missing = array();
        if (isset($package['requires']) && is_array($package['requires'])) {
            foreach ($package['requires'] as $required_id) {
                if (!isset($this->config['installed'][$required_id])) {
                    $missing[] = isset($this->packages[$required_id])
                        ? $this->lang($this->packages[$required_id]['title_key'], $required_id)
                        : $required_id;
                }
            }
        }
        return $missing;
    }

    /**
     * Update an installed package to the version from packages/.
     *
     * Updates are additive and conservative:
     * - new classes / objects / scripts / scenes / layouts / text files are created;
     * - new properties and methods are added to classes/objects created by this module;
     * - existing scripts, layouts and text files are updated only if they were
     *   not modified by the user;
     * - existing scenes are never rewritten (use uninstall/install to reset).
     */
    function updatePackage($package_id)
    {
        $result = array('ok' => false, 'messages' => array());
        if (!isset($this->packages[$package_id])) {
            $result['messages'][] = array('CLASS' => 'danger', 'TEXT' => 'Unknown package: ' . $package_id);
            return $result;
        }
        if (!isset($this->config['installed'][$package_id])) {
            $result['messages'][] = array(
                'CLASS' => 'warning',
                'TEXT' => $this->lang($this->packages[$package_id]['title_key'], $package_id) . ': ' .
                    $this->lang('STARTER_KIT_UPDATE_NOT_INSTALLED')
            );
            return $result;
        }

        $package = $this->packages[$package_id];
        $this->texts = isset($package['texts']) && is_array($package['texts']) ? $package['texts'] : array();
        $record = $this->config['installed'][$package_id];
        $current_version = isset($record['version']) ? (int)$record['version'] : 0;
        $package_version = isset($package['version']) ? (int)$package['version'] : 1;
        $title = $this->lang($package['title_key'], $package_id);

        if ($package_version <= $current_version) {
            $result['ok'] = true;
            $result['messages'][] = array(
                'CLASS' => 'info',
                'TEXT' => $title . ': ' . $this->lang('STARTER_KIT_UPDATE_UP_TO_DATE')
            );
            return $result;
        }

        $missing = $this->getMissingRequirements($package);
        if (count($missing)) {
            $result['messages'][] = array(
                'CLASS' => 'danger',
                'TEXT' => $title . ': ' . $this->lang('STARTER_KIT_REQUIRES') . ' ' . implode(', ', $missing) .
                    ' (' . $this->lang('STARTER_KIT_REQUIRES_MISSING') . ')'
            );
            return $result;
        }

        // Only new top-level entities take part in conflict detection.
        $filtered = array('id' => $package_id, 'texts' => isset($package['texts']) ? $package['texts'] : array());
        foreach (array('classes', 'objects', 'scripts', 'scenes', 'layouts', 'text_files') as $type) {
            if (!isset($package[$type]) || !is_array($package[$type])) {
                continue;
            }
            foreach ($package[$type] as $entity) {
                $key = ($type == 'text_files') ? $entity['name'] : $entity['title'];
                if (!isset($record['entities'][$type][$key])) {
                    $filtered[$type][] = $entity;
                }
            }
        }
        $conflicts = $this->detectConflicts($filtered);
        if (count($conflicts)) {
            $result['messages'][] = array(
                'CLASS' => 'danger',
                'TEXT' => $title . ': ' . $this->lang('STARTER_KIT_CONFLICT') . ' (' . implode(', ', $conflicts) . ')'
            );
            return $result;
        }

        $skipped = array();
        $added = 0;

        // Existing classes may get new properties and methods.
        if (isset($package['classes']) && is_array($package['classes'])) {
            foreach ($package['classes'] as $entity) {
                if (isset($record['entities']['classes'][$entity['title']])) {
                    $this->upgradeClass($entity, $record, $skipped);
                } else {
                    $new_record = $this->createClass($entity);
                    if ($new_record === false) {
                        $this->config['installed'][$package_id] = $record;
                        $result['messages'][] = array(
                            'CLASS' => 'danger',
                            'TEXT' => $title . ': cannot create class ' . $entity['title']
                        );
                        return $result;
                    }
                    $record['entities']['classes'][$entity['title']] = $new_record;
                    $added++;
                }
            }
        }

        if (isset($package['objects']) && is_array($package['objects'])) {
            foreach ($package['objects'] as $entity) {
                if (isset($record['entities']['objects'][$entity['title']])) {
                    $this->upgradeObject($entity, $record, $skipped);
                } else {
                    $new_record = $this->createObject($entity);
                    if ($new_record === false) {
                        $this->config['installed'][$package_id] = $record;
                        $result['messages'][] = array(
                            'CLASS' => 'danger',
                            'TEXT' => $title . ': cannot create object ' . $entity['title']
                        );
                        return $result;
                    }
                    $record['entities']['objects'][$entity['title']] = $new_record;
                    $added++;
                }
            }
        }

        if (isset($package['text_files']) && is_array($package['text_files'])) {
            foreach ($package['text_files'] as $entity) {
                if (isset($record['entities']['text_files'][$entity['name']])) {
                    $this->upgradeTextFile($entity, $record, $skipped);
                } else {
                    $new_record = $this->createTextFile($entity);
                    if ($new_record === false) {
                        $this->config['installed'][$package_id] = $record;
                        $result['messages'][] = array(
                            'CLASS' => 'danger',
                            'TEXT' => $title . ': cannot create text file ' . $entity['name']
                        );
                        return $result;
                    }
                    $record['entities']['text_files'][$entity['name']] = $new_record;
                    $added++;
                }
            }
        }

        if (isset($package['scripts']) && is_array($package['scripts'])) {
            foreach ($package['scripts'] as $entity) {
                if (isset($record['entities']['scripts'][$entity['title']])) {
                    $this->upgradeScript($entity, $record, $skipped);
                } else {
                    $new_record = $this->createScript($entity);
                    if ($new_record === false) {
                        $this->config['installed'][$package_id] = $record;
                        $result['messages'][] = array(
                            'CLASS' => 'danger',
                            'TEXT' => $title . ': cannot create script ' . $entity['title']
                        );
                        return $result;
                    }
                    $record['entities']['scripts'][$entity['title']] = $new_record;
                    $added++;
                }
            }
        }

        if (isset($package['scenes']) && is_array($package['scenes'])) {
            foreach ($package['scenes'] as $entity) {
                $scene_title = $this->resolveText($entity['title']);
                if (isset($record['entities']['scenes'][$scene_title])) {
                    // Existing scenes are intentionally not rewritten.
                    continue;
                }
                $new_record = $this->createScene($entity);
                if ($new_record === false) {
                    $this->config['installed'][$package_id] = $record;
                    $result['messages'][] = array(
                        'CLASS' => 'danger',
                        'TEXT' => $title . ': cannot create scene ' . $scene_title
                    );
                    return $result;
                }
                $record['entities']['scenes'][$new_record['title']] = $new_record;
                $added++;
            }
        }

        if (isset($package['layouts']) && is_array($package['layouts'])) {
            foreach ($package['layouts'] as $entity) {
                $layout_title = $this->resolveText($entity['title']);
                if (isset($record['entities']['layouts'][$layout_title])) {
                    $this->upgradeLayout($entity, $record, $skipped);
                } else {
                    $new_record = $this->createLayout($entity);
                    if ($new_record === false) {
                        $this->config['installed'][$package_id] = $record;
                        $result['messages'][] = array(
                            'CLASS' => 'danger',
                            'TEXT' => $title . ': cannot create layout ' . $layout_title
                        );
                        return $result;
                    }
                    $record['entities']['layouts'][$new_record['title']] = $new_record;
                    $added++;
                }
            }
        }

        $record['version'] = $package_version;
        $record['updated_at'] = date('Y-m-d H:i:s');
        $this->config['installed'][$package_id] = $record;
        $result['ok'] = true;
        $result['messages'][] = array(
            'CLASS' => 'success',
            'TEXT' => $title . ': ' . $this->lang('STARTER_KIT_UPDATE_DONE') . ' v' .
                $current_version . ' &rarr; v' . $package_version . ' (' . $added . ')'
        );
        if (count($skipped)) {
            $result['messages'][] = array(
                'CLASS' => 'warning',
                'TEXT' => $title . ': ' . $this->lang('STARTER_KIT_UPDATE_SKIPPED') . ' (' . implode(', ', $skipped) . ')'
            );
        }
        return $result;
    }

    /**
     * Add new properties/methods/template to an existing class. Existing items
     * and user-edited code are never overwritten.
     */
    function upgradeClass($entity, &$record, &$skipped)
    {
        $title = $entity['title'];
        if (!isset($record['entities']['classes'][$title])) {
            return;
        }
        $meta = $record['entities']['classes'][$title];
        $rec = SQLSelectOne("SELECT * FROM classes WHERE ID=" . (int)$meta['id']);
        if (!$rec['ID'] || $rec['TITLE'] != $title) {
            $skipped[] = $title;
            return;
        }

        if (isset($entity['properties']) && is_array($entity['properties'])) {
            foreach ($entity['properties'] as $name => $options) {
                if (isset($meta['properties'][$name])) {
                    continue;
                }
                $options = is_array($options) ? $options : array();
                $keep_history = isset($options['keep_history']) ? (int)$options['keep_history'] : 0;
                $onchange = isset($options['onchange']) ? trim($options['onchange']) : '';
                $property_id = addClassProperty($title, $name, $keep_history);
                if (!$property_id) {
                    continue;
                }
                if ($onchange != '' || isset($options['description'])) {
                    $property_rec = SQLSelectOne("SELECT * FROM properties WHERE ID=" . (int)$property_id);
                    if ($property_rec['ID']) {
                        if ($onchange != '') {
                            $property_rec['ONCHANGE'] = $onchange;
                        }
                        if (isset($options['description'])) {
                            $property_rec['DESCRIPTION'] = $this->resolveText($options['description']);
                        }
                        SQLUpdate('properties', $property_rec);
                    }
                }
                $meta['properties'][$name] = array('id' => (int)$property_id, 'onchange' => $onchange);
            }
        }

        if (isset($entity['methods']) && is_array($entity['methods'])) {
            foreach ($entity['methods'] as $name => $raw_code) {
                if (isset($meta['methods'][$name])) {
                    continue;
                }
                $code = $this->resolveText($raw_code);
                $method_id = addClassMethod($title, $name, $code);
                if ($method_id) {
                    $meta['methods'][$name] = array('id' => (int)$method_id, 'hash' => md5(trim($code)));
                }
            }
        }

        if (isset($entity['template'])) {
            $template = $this->resolveText($entity['template']);
            if (isset($meta['template_hash'])) {
                if (md5(trim((string)$rec['TEMPLATE'])) == $meta['template_hash']) {
                    if ((string)$rec['TEMPLATE'] !== $template) {
                        $rec['TEMPLATE'] = $template;
                        SQLUpdate('classes', $rec);
                    }
                    $meta['template_hash'] = md5(trim($template));
                } else {
                    $skipped[] = $title . '.template';
                }
            } elseif (trim((string)$rec['TEMPLATE']) == '') {
                $rec['TEMPLATE'] = $template;
                SQLUpdate('classes', $rec);
                $meta['template_hash'] = md5(trim($template));
            } else {
                $skipped[] = $title . '.template';
            }
        }

        $record['entities']['classes'][$title] = $meta;
        $this->refreshClassTree();
    }

    /**
     * Add new object-level methods to an existing object. Property values are
     * never touched: they may already contain user data.
     */
    function upgradeObject($entity, &$record, &$skipped)
    {
        $title = $entity['title'];
        if (!isset($record['entities']['objects'][$title])) {
            return;
        }
        $meta = $record['entities']['objects'][$title];
        $rec = SQLSelectOne("SELECT * FROM objects WHERE ID=" . (int)$meta['id']);
        if (!$rec['ID'] || $rec['TITLE'] != $title) {
            $skipped[] = $title;
            return;
        }
        if (isset($entity['methods']) && is_array($entity['methods'])) {
            foreach ($entity['methods'] as $name => $raw_code) {
                if (isset($meta['methods'][$name])) {
                    continue;
                }
                $code = $this->resolveText($raw_code);
                $method = array(
                    'OBJECT_ID' => (int)$meta['id'],
                    'CLASS_ID' => 0,
                    'TITLE' => $name,
                    'CODE' => $code
                );
                $method['ID'] = SQLInsert('methods', $method);
                if ($method['ID']) {
                    $meta['methods'][$name] = array('id' => (int)$method['ID'], 'hash' => md5(trim($code)));
                }
            }
        }

        // Set defaults for properties that have no value yet (new in this
        // package version). Existing values are never overwritten.
        if (isset($entity['properties']) && is_array($entity['properties'])) {
            foreach ($entity['properties'] as $name => $value) {
                $value_rec = SQLSelectOne("SELECT ID FROM pvalues WHERE PROPERTY_NAME='" .
                    DBSafe($title . '.' . $name) . "'");
                if (!$value_rec['ID']) {
                    setGlobal($title . '.' . $name, $this->resolveText($value));
                }
            }
        }

        $record['entities']['objects'][$title] = $meta;
    }

    function upgradeScript($entity, &$record, &$skipped)
    {
        $title = $entity['title'];
        $meta = $record['entities']['scripts'][$title];
        $rec = SQLSelectOne("SELECT * FROM scripts WHERE ID=" . (int)$meta['id']);
        if (!$rec['ID'] || $rec['TITLE'] != $title) {
            $skipped[] = $title;
            return;
        }
        if (md5(trim($rec['CODE'])) != $meta['hash']) {
            $skipped[] = $title;
            return;
        }
        $code = $this->resolveText($entity['code']);
        if ($code !== $rec['CODE']) {
            $rec['CODE'] = $code;
            $rec['DESCRIPTION'] = isset($entity['description']) ? $this->resolveText($entity['description']) : $rec['DESCRIPTION'];
            SQLUpdate('scripts', $rec);
        }
        $meta['hash'] = md5(trim($code));
        $record['entities']['scripts'][$title] = $meta;
    }

    function upgradeTextFile($entity, &$record, &$skipped)
    {
        $name = $entity['name'];
        $meta = $record['entities']['text_files'][$name];
        $path = ROOT . $meta['file'];
        if (!is_file($path)) {
            return;
        }
        $current = @file_get_contents($path);
        if ($current === false || md5(trim($current)) != $meta['hash']) {
            $skipped[] = $name;
            return;
        }
        $content = $this->resolveText($entity['content']);
        if ($content !== $current) {
            if (!$this->writeManagedFile($meta['file'], $content)) {
                $skipped[] = $name;
                return;
            }
        }
        $meta['hash'] = md5(trim($content));
        $record['entities']['text_files'][$name] = $meta;
    }

    function upgradeLayout($entity, &$record, &$skipped)
    {
        $title = $this->resolveText($entity['title']);
        $meta = $record['entities']['layouts'][$title];
        $rec = SQLSelectOne("SELECT * FROM layouts WHERE ID=" . (int)$meta['id']);
        if (!$rec['ID'] || $rec['TITLE'] != $title) {
            $skipped[] = $title;
            return;
        }
        if (md5(trim($rec['CODE'])) != $meta['hash']) {
            $skipped[] = $title;
            return;
        }
        if (!empty($meta['file'])) {
            $path = ROOT . $meta['file'];
            if (is_file($path)) {
                $current = @file_get_contents($path);
                if ($current === false || md5(trim($current)) != $meta['hash']) {
                    $skipped[] = $title;
                    return;
                }
            }
        }
        $code = $this->resolveText($entity['code']);
        if ($code !== $rec['CODE']) {
            $rec['CODE'] = $code;
            SQLUpdate('layouts', $rec);
            if (!empty($meta['file'])) {
                $this->writeManagedFile($meta['file'], $code);
            }
        }
        $meta['hash'] = md5(trim($code));
        $record['entities']['layouts'][$title] = $meta;
    }

    function createClass($entity)
    {
        $class_id = addClass($entity['title'], isset($entity['parent']) ? $entity['parent'] : '');
        $class_rec = SQLSelectOne("SELECT * FROM classes WHERE ID=" . (int)$class_id);
        if (!$class_rec['ID'] || $class_rec['TITLE'] != $entity['title']) {
            return false;
        }
        if (isset($entity['description'])) {
            $class_rec['DESCRIPTION'] = $this->resolveText($entity['description']);
            SQLUpdate('classes', $class_rec);
        }
        if (isset($entity['template'])) {
            $class_rec['TEMPLATE'] = $this->resolveText($entity['template']);
            SQLUpdate('classes', $class_rec);
        }

        $record = array(
            'id' => (int)$class_id,
            'title' => $entity['title'],
            'properties' => array(),
            'methods' => array()
        );
        if (isset($entity['template'])) {
            $record['template_hash'] = md5(trim($class_rec['TEMPLATE']));
        }

        if (isset($entity['properties']) && is_array($entity['properties'])) {
            foreach ($entity['properties'] as $name => $options) {
                $options = is_array($options) ? $options : array();
                $keep_history = isset($options['keep_history']) ? (int)$options['keep_history'] : 0;
                $onchange = isset($options['onchange']) ? trim($options['onchange']) : '';
                $property_id = addClassProperty($entity['title'], $name, $keep_history);
                if (!$property_id) {
                    continue;
                }
                if ($onchange != '' || isset($options['description'])) {
                    $property_rec = SQLSelectOne("SELECT * FROM properties WHERE ID=" . (int)$property_id);
                    if ($property_rec['ID']) {
                        if ($onchange != '') {
                            $property_rec['ONCHANGE'] = $onchange;
                        }
                        if (isset($options['description'])) {
                            $property_rec['DESCRIPTION'] = $this->resolveText($options['description']);
                        }
                        SQLUpdate('properties', $property_rec);
                    }
                }
                // Array form keeps the onchange hook for safe uninstall; the
                // plain integer form of older packages is still supported.
                $record['properties'][$name] = array(
                    'id' => (int)$property_id,
                    'onchange' => $onchange
                );
            }
        }

        if (isset($entity['methods']) && is_array($entity['methods'])) {
            foreach ($entity['methods'] as $name => $raw_code) {
                $code = $this->resolveText($raw_code);
                $method_id = addClassMethod($entity['title'], $name, $code);
                if ($method_id) {
                    $record['methods'][$name] = array(
                        'id' => (int)$method_id,
                        'hash' => md5(trim($code))
                    );
                }
            }
        }

        $this->refreshClassTree();
        return $record;
    }

    function createObject($entity)
    {
        $class_rec = SQLSelectOne("SELECT * FROM classes WHERE TITLE='" . DBSafe($entity['class']) . "'");
        if (!$class_rec['ID']) {
            return false;
        }
        $object_id = addClassObject($entity['class'], $entity['title']);
        $object_rec = SQLSelectOne("SELECT * FROM objects WHERE ID=" . (int)$object_id);
        if (!$object_rec['ID'] || $object_rec['TITLE'] != $entity['title']) {
            return false;
        }
        if (isset($entity['description'])) {
            $object_rec['DESCRIPTION'] = $this->resolveText($entity['description']);
            SQLUpdate('objects', $object_rec);
        }

        $record = array(
            'id' => (int)$object_id,
            'title' => $entity['title'],
            'class' => $entity['class'],
            'properties' => array(),
            'methods' => array()
        );

        if (isset($entity['properties']) && is_array($entity['properties'])) {
            foreach ($entity['properties'] as $name => $value) {
                setGlobal($entity['title'] . '.' . $name, $this->resolveText($value));
            }
        }

        if (isset($entity['methods']) && is_array($entity['methods'])) {
            foreach ($entity['methods'] as $name => $raw_code) {
                $code = $this->resolveText($raw_code);
                $method = array(
                    'OBJECT_ID' => (int)$object_id,
                    'CLASS_ID' => 0,
                    'TITLE' => $name,
                    'CODE' => $code
                );
                $method['ID'] = SQLInsert('methods', $method);
                $record['methods'][$name] = array(
                    'id' => (int)$method['ID'],
                    'hash' => md5(trim($code))
                );
            }
        }

        return $record;
    }

    function createScript($entity)
    {
        $code = $this->resolveText($entity['code']);
        $script = array(
            'TITLE' => $entity['title'],
            'CODE' => $code,
            'DESCRIPTION' => isset($entity['description']) ? $this->resolveText($entity['description']) : '',
            'TYPE' => 0
        );
        $script['ID'] = SQLInsert('scripts', $script);
        return array(
            'id' => (int)$script['ID'],
            'title' => $entity['title'],
            'hash' => md5(trim($code))
        );
    }

    /**
     * Create a text file in cms/texts/<name>.txt. These files are managed by
     * the "Text files" module and can be read by scripts with LoadFile().
     *
     * Expected package format:
     *   'text_files' => array(
     *       array('name' => 'starter_kit_help', 'content' => '...')
     *   )
     */
    function createTextFile($entity)
    {
        $name = isset($entity['name']) ? trim($entity['name']) : '';
        if (!preg_match('/^[A-Za-z0-9_\-]+$/', $name)) {
            return false;
        }
        $content = isset($entity['content']) ? $this->resolveText($entity['content']) : '';
        $relative = 'cms/texts/' . $name . '.txt';
        if (!$this->writeManagedFile($relative, $content)) {
            return false;
        }
        return array(
            'name' => $name,
            'file' => $relative,
            'hash' => md5(trim($content))
        );
    }

    /**
     * Write a runtime file relative to ROOT, creating the directory if needed.
     * Refuses paths outside the allowed cms subdirectories.
     */
    function writeManagedFile($relative, $content)
    {
        if (!defined('ROOT') || !is_string($relative)) {
            return false;
        }
        if (strpos($relative, 'cms/texts/') !== 0 && strpos($relative, 'cms/layouts/') !== 0) {
            return false;
        }
        $path = ROOT . $relative;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $written = @file_put_contents($path, $content);
        if ($written === false) {
            return false;
        }
        @chmod($path, 0644);
        return true;
    }

    function deleteManagedFile($relative)
    {
        if (!defined('ROOT') || !is_string($relative)) {
            return;
        }
        if (strpos($relative, 'cms/texts/') !== 0 && strpos($relative, 'cms/layouts/') !== 0) {
            return;
        }
        $path = ROOT . $relative;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Create a scene with button elements. Each element runs an object method
     * on click (scenes module: ACTION_OBJECT + ACTION_METHOD).
     *
     * Expected package format:
     *   'scenes' => array(
     *       array(
     *           'title' => '...', 'priority' => 10,
     *           'elements' => array(
     *               array('title' => '...', 'type' => 'button',
     *                     'top' => 100, 'left' => 100, 'width' => 240, 'height' => 60,
     *                     'action_object' => 'StarterScenes', 'action_method' => 'goodMorning')
     *           )
     *       )
     *   )
     */
    function createScene($entity)
    {
        $title = $this->resolveText($entity['title']);
        $scene = array(
            'TITLE' => $title,
            'PRIORITY' => isset($entity['priority']) ? (int)$entity['priority'] : 0,
            'HIDDEN' => 0
        );
        $scene['ID'] = SQLInsert('scenes', $scene);
        if (!$scene['ID']) {
            return false;
        }

        $record = array(
            'id' => (int)$scene['ID'],
            'title' => $title,
            'elements' => array()
        );
        $elements = isset($entity['elements']) && is_array($entity['elements']) ? $entity['elements'] : array();
        $priority = 10;
        foreach ($elements as $element) {
            $element_title = isset($element['title']) ? $this->resolveText($element['title']) : '';
            $element_rec = array(
                'SCENE_ID' => (int)$scene['ID'],
                'TITLE' => $element_title,
                'TYPE' => isset($element['type']) ? $element['type'] : 'button',
                'TOP' => isset($element['top']) ? (int)$element['top'] : 100,
                'LEFT' => isset($element['left']) ? (int)$element['left'] : 100,
                'WIDTH' => isset($element['width']) ? (int)$element['width'] : 200,
                'HEIGHT' => isset($element['height']) ? (int)$element['height'] : 60,
                'CSS_STYLE' => 'default',
                'PRIORITY' => $priority
            );
            $element_rec['ID'] = SQLInsert('elements', $element_rec);
            if (!$element_rec['ID']) {
                $this->deleteSceneRaw($scene['ID']);
                return false;
            }
            $priority += 10;

            $state = array(
                'ELEMENT_ID' => (int)$element_rec['ID'],
                'TITLE' => 'default',
                'HTML' => $element_title
            );
            if (!empty($element['action_object']) && !empty($element['action_method'])) {
                $state['ACTION_OBJECT'] = $element['action_object'];
                $state['ACTION_METHOD'] = $element['action_method'];
            }
            $state['ID'] = SQLInsert('elm_states', $state);
            if (!$state['ID']) {
                $this->deleteSceneRaw($scene['ID']);
                return false;
            }

            $record['elements'][] = array(
                'id' => (int)$element_rec['ID'],
                'state_id' => (int)$state['ID'],
                'title' => $element_title,
                'html' => $element_title,
                'action_object' => isset($state['ACTION_OBJECT']) ? $state['ACTION_OBJECT'] : '',
                'action_method' => isset($state['ACTION_METHOD']) ? $state['ACTION_METHOD'] : ''
            );
        }
        return $record;
    }

    /**
     * Create a frontend page (layouts table, TYPE=html) and its cached HTML
     * file in cms/layouts/<ID>.html. The layouts module includes that file.
     *
     * Expected package format:
     *   'layouts' => array(
     *       array(
     *           'title' => '...', 'type' => 'html', 'priority' => 50,
     *           'code' => '<div>... %Object.Property|10% ...</div>'
     *       )
     *   )
     */
    function createLayout($entity)
    {
        $title = $this->resolveText($entity['title']);
        $code = $this->resolveText($entity['code']);
        $type = isset($entity['type']) ? $entity['type'] : 'html';
        $layout = array(
            'TITLE' => $title,
            'TYPE' => $type,
            'PRIORITY' => isset($entity['priority']) ? (int)$entity['priority'] : 0,
            'HIDDEN' => isset($entity['hidden']) ? (int)$entity['hidden'] : 0,
            'CODE' => $code
        );
        $layout['ID'] = SQLInsert('layouts', $layout);
        if (!$layout['ID']) {
            return false;
        }

        $file = '';
        if ($type == 'html') {
            $file = 'cms/layouts/' . (int)$layout['ID'] . '.html';
            if (!$this->writeLayoutFile($file, $code)) {
                SQLExec("DELETE FROM layouts WHERE ID=" . (int)$layout['ID']);
                return false;
            }
        }

        return array(
            'id' => (int)$layout['ID'],
            'title' => $title,
            'file' => $file,
            'hash' => md5(trim($code))
        );
    }

    function writeLayoutFile($relative, $code)
    {
        if (!defined('ROOT')) {
            return false;
        }
        $path = ROOT . $relative;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $written = @file_put_contents($path, $code);
        if ($written === false) {
            return false;
        }
        @chmod($path, 0644);
        return true;
    }

    function deleteLayoutFile($relative)
    {
        if (!defined('ROOT') || !is_string($relative) || strpos($relative, 'cms/layouts/') !== 0) {
            return;
        }
        $path = ROOT . $relative;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Removes entities created during a failed install attempt.
     */
    function rollbackRecord($record)
    {
        $fake = array('entities' => $record['entities']);
        foreach (array('scripts', 'text_files', 'layouts', 'scenes', 'objects', 'classes') as $type) {
            if (!isset($fake['entities'][$type])) {
                continue;
            }
            foreach ($fake['entities'][$type] as $entity) {
                if ($type == 'scripts') {
                    SQLExec("DELETE FROM scripts WHERE ID=" . (int)$entity['id']);
                } elseif ($type == 'text_files') {
                    $this->deleteManagedFile(isset($entity['file']) ? $entity['file'] : '');
                } elseif ($type == 'layouts') {
                    SQLExec("DELETE FROM layouts WHERE ID=" . (int)$entity['id']);
                    $this->deleteLayoutFile(isset($entity['file']) ? $entity['file'] : '');
                } elseif ($type == 'scenes') {
                    $this->deleteSceneRaw($entity['id']);
                } elseif ($type == 'objects') {
                    deleteObject((int)$entity['id']);
                } elseif ($type == 'classes') {
                    $this->deleteClassIfSafe($entity);
                }
            }
        }
    }

    function uninstallPackage($package_id)
    {
        $result = array('ok' => false, 'messages' => array());
        if (!isset($this->config['installed'][$package_id])) {
            $result['ok'] = true;
            return $result;
        }
        $package = $this->packages[$package_id];
        $record = $this->config['installed'][$package_id];

        $required_by = array();
        foreach ($this->packages as $other_id => $other_package) {
            if ($other_id == $package_id || !isset($this->config['installed'][$other_id])) {
                continue;
            }
            if (isset($other_package['requires']) && in_array($package_id, $other_package['requires'], true)) {
                $required_by[] = $this->lang($other_package['title_key'], $other_id);
            }
        }
        if (count($required_by)) {
            $result['messages'][] = array(
                'CLASS' => 'danger',
                'TEXT' => $this->lang($package['title_key'], $package_id) . ': ' .
                    $this->lang('STARTER_KIT_REQUIRED_BY') . ' ' . implode(', ', $required_by) .
                    '. ' . $this->lang('STARTER_KIT_UNINSTALL_DEPENDENTS')
            );
            return $result;
        }

        $skipped = array();

        // Reverse order: scripts, text files, layouts, scenes, objects, classes.
        foreach (array('scripts', 'text_files', 'layouts', 'scenes', 'objects', 'classes') as $type) {
            if (!isset($record['entities'][$type])) {
                continue;
            }
            foreach ($record['entities'][$type] as $title => $entity) {
                $status = 'removed';
                if ($type == 'scripts') {
                    $status = $this->removeScript($entity);
                } elseif ($type == 'text_files') {
                    $status = $this->removeTextFile($entity);
                } elseif ($type == 'layouts') {
                    $status = $this->removeLayout($entity);
                } elseif ($type == 'scenes') {
                    $status = $this->removeScene($entity);
                } elseif ($type == 'objects') {
                    $status = $this->removeObject($entity);
                } elseif ($type == 'classes') {
                    $status = $this->removeClass($entity);
                }
                if ($status == 'removed') {
                    unset($record['entities'][$type][$title]);
                } else {
                    $skipped[] = isset($entity['title']) ? $entity['title'] : (isset($entity['name']) ? $entity['name'] : $title);
                }
            }
        }

        $empty = true;
        foreach (array('scripts', 'text_files', 'layouts', 'scenes', 'objects', 'classes') as $type) {
            if (isset($record['entities'][$type]) && count($record['entities'][$type])) {
                $empty = false;
            }
        }

        if ($empty) {
            unset($this->config['installed'][$package_id]);
            $result['ok'] = true;
            $result['messages'][] = array(
                'CLASS' => 'success',
                'TEXT' => $this->lang($package['title_key'], $package_id) . ': ' . $this->lang('STARTER_KIT_UNINSTALL_DONE')
            );
        } else {
            $this->config['installed'][$package_id] = $record;
            $result['messages'][] = array(
                'CLASS' => 'warning',
                'TEXT' => $this->lang($package['title_key'], $package_id) . ': ' .
                    $this->lang('STARTER_KIT_SKIPPED_MODIFIED') . ' (' . implode(', ', $skipped) . ')'
            );
        }
        return $result;
    }

    function removeScript($entity)
    {
        $rec = SQLSelectOne("SELECT * FROM scripts WHERE ID=" . (int)$entity['id']);
        if (!$rec['ID']) {
            return 'removed';
        }
        if ($rec['TITLE'] != $entity['title'] || md5(trim($rec['CODE'])) != $entity['hash']) {
            return 'skipped';
        }
        SQLExec("DELETE FROM scripts WHERE ID=" . (int)$entity['id']);
        return 'removed';
    }

    /**
     * Remove a managed text file only if its content is unchanged.
     */
    function removeTextFile($entity)
    {
        $relative = isset($entity['file']) ? $entity['file'] : '';
        if ($relative == '' || !defined('ROOT')) {
            return 'removed';
        }
        if (strpos($relative, 'cms/texts/') !== 0) {
            return 'skipped';
        }
        $path = ROOT . $relative;
        if (!is_file($path)) {
            return 'removed';
        }
        $content = @file_get_contents($path);
        if ($content === false || md5(trim($content)) != $entity['hash']) {
            return 'skipped'; // user edited the text file
        }
        @unlink($path);
        return 'removed';
    }

    /**
     * Remove a layout page only if the row title and CODE are unchanged and
     * the generated file (if any) still matches. The file is removed too.
     */
    function removeLayout($entity)
    {
        $rec = SQLSelectOne("SELECT * FROM layouts WHERE ID=" . (int)$entity['id']);
        if (!$rec['ID']) {
            $this->deleteLayoutFile(isset($entity['file']) ? $entity['file'] : '');
            return 'removed';
        }
        if ($rec['TITLE'] != $entity['title']) {
            return 'skipped';
        }
        if (md5(trim($rec['CODE'])) != $entity['hash']) {
            return 'skipped'; // user edited the page in the admin panel
        }
        if (!empty($entity['file']) && defined('ROOT')) {
            $path = ROOT . $entity['file'];
            if (is_file($path)) {
                $content = @file_get_contents($path);
                if ($content === false || md5(trim($content)) != $entity['hash']) {
                    return 'skipped'; // user edited the cached html file
                }
            }
        }
        SQLExec("DELETE FROM layouts WHERE ID=" . (int)$entity['id']);
        $this->deleteLayoutFile(isset($entity['file']) ? $entity['file'] : '');
        return 'removed';
    }

    /**
     * Remove a scene only if it still looks exactly as installed: same title,
     * same set of elements, same button labels and actions.
     */
    function removeScene($entity)
    {
        $scene = SQLSelectOne("SELECT * FROM scenes WHERE ID=" . (int)$entity['id']);
        if (!$scene['ID']) {
            return 'removed';
        }
        if ($scene['TITLE'] != $entity['title']) {
            return 'skipped';
        }
        $elements = SQLSelect("SELECT * FROM elements WHERE SCENE_ID=" . (int)$entity['id']);
        $stored = isset($entity['elements']) && is_array($entity['elements']) ? $entity['elements'] : array();
        if (count($elements) != count($stored)) {
            return 'skipped'; // user added or removed elements
        }
        $stored_by_id = array();
        foreach ($stored as $item) {
            $stored_by_id[(int)$item['id']] = $item;
        }
        foreach ($elements as $element) {
            $element_id = (int)$element['ID'];
            if (!isset($stored_by_id[$element_id])) {
                return 'skipped';
            }
            $expected = $stored_by_id[$element_id];
            $expected_title = isset($expected['title']) ? (string)$expected['title'] : '';
            $expected_html = isset($expected['html']) ? (string)$expected['html'] : '';
            $expected_object = isset($expected['action_object']) ? (string)$expected['action_object'] : '';
            $expected_method = isset($expected['action_method']) ? (string)$expected['action_method'] : '';
            if ((string)$element['TITLE'] !== $expected_title) {
                return 'skipped';
            }
            if (!isset($expected['state_id'])) {
                return 'skipped';
            }
            $state = SQLSelectOne("SELECT * FROM elm_states WHERE ID=" . (int)$expected['state_id']);
            if (!$state['ID']) {
                return 'skipped';
            }
            if ((string)$state['HTML'] !== $expected_html) {
                return 'skipped'; // user changed the button label
            }
            if ((string)$state['ACTION_OBJECT'] !== $expected_object) {
                return 'skipped';
            }
            if ((string)$state['ACTION_METHOD'] !== $expected_method) {
                return 'skipped';
            }
        }
        $this->deleteSceneRaw($entity['id']);
        return 'removed';
    }

    /**
     * Delete a scene with all its elements and element states (no safety checks).
     */
    function deleteSceneRaw($scene_id)
    {
        $elements = SQLSelect("SELECT ID FROM elements WHERE SCENE_ID=" . (int)$scene_id);
        if (is_array($elements)) {
            foreach ($elements as $element) {
                SQLExec("DELETE FROM elm_states WHERE ELEMENT_ID=" . (int)$element['ID']);
            }
        }
        SQLExec("DELETE FROM elements WHERE SCENE_ID=" . (int)$scene_id);
        SQLExec("DELETE FROM scenes WHERE ID=" . (int)$scene_id);
    }

    function removeObject($entity)
    {
        $rec = SQLSelectOne("SELECT * FROM objects WHERE ID=" . (int)$entity['id']);
        if (!$rec['ID']) {
            return 'removed';
        }
        if ($rec['TITLE'] != $entity['title']) {
            return 'skipped';
        }
        $properties = SQLSelect("SELECT ID, TITLE FROM properties WHERE OBJECT_ID=" . (int)$entity['id']);
        foreach ($properties as $property) {
            if (!isset($entity['properties'][$property['TITLE']])) {
                return 'skipped'; // user added an object-level property
            }
        }
        $methods = SQLSelect("SELECT ID, TITLE FROM methods WHERE OBJECT_ID=" . (int)$entity['id']);
        foreach ($methods as $method) {
            if (!isset($entity['methods'][$method['TITLE']])) {
                return 'skipped'; // user added an object-level method
            }
        }
        deleteObject((int)$entity['id']);
        return 'removed';
    }

    function removeClass($entity)
    {
        $rec = SQLSelectOne("SELECT * FROM classes WHERE ID=" . (int)$entity['id']);
        if (!$rec['ID']) {
            return 'removed';
        }
        if ($rec['TITLE'] != $entity['title']) {
            return 'skipped';
        }
        if (isset($entity['template_hash']) && md5(trim((string)$rec['TEMPLATE'])) != $entity['template_hash']) {
            return 'skipped'; // user changed the class display template
        }
        $child = SQLSelectOne("SELECT ID FROM classes WHERE PARENT_ID=" . (int)$entity['id']);
        if ($child['ID']) {
            return 'skipped'; // subclasses exist
        }
        $object = SQLSelectOne("SELECT ID FROM objects WHERE CLASS_ID=" . (int)$entity['id']);
        if ($object['ID']) {
            return 'skipped'; // still used by objects
        }
        $properties = SQLSelect("SELECT ID, TITLE, ONCHANGE FROM properties WHERE CLASS_ID=" . (int)$entity['id'] . " AND OBJECT_ID=0");
        foreach ($properties as $property) {
            if (!isset($entity['properties'][$property['TITLE']])) {
                return 'skipped'; // user added a class property
            }
            $meta = $entity['properties'][$property['TITLE']];
            if (is_array($meta) && isset($meta['onchange']) && $meta['onchange'] != '' && $property['ONCHANGE'] != $meta['onchange']) {
                return 'skipped'; // user changed the onchange hook
            }
        }
        $methods = SQLSelect("SELECT ID, TITLE, CODE FROM methods WHERE CLASS_ID=" . (int)$entity['id'] . " AND OBJECT_ID=0");
        foreach ($methods as $method) {
            if (!isset($entity['methods'][$method['TITLE']])) {
                return 'skipped'; // user added a class method
            }
            if (md5(trim($method['CODE'])) != $entity['methods'][$method['TITLE']]['hash']) {
                return 'skipped'; // user changed generated code
            }
        }
        $this->deleteClassIfSafe($entity);
        return 'removed';
    }

    function deleteClassIfSafe($entity)
    {
        $rec = SQLSelectOne("SELECT * FROM classes WHERE ID=" . (int)$entity['id']);
        if (!$rec['ID']) {
            return;
        }
        SQLExec("DELETE FROM properties WHERE CLASS_ID=" . (int)$entity['id'] . " AND OBJECT_ID=0");
        SQLExec("DELETE FROM methods WHERE CLASS_ID=" . (int)$entity['id'] . " AND OBJECT_ID=0");
        SQLExec("DELETE FROM classes WHERE ID=" . (int)$entity['id']);
        $this->refreshClassTree();
    }

    function refreshClassTree()
    {
        include_once(DIR_MODULES . 'classes/classes.class.php');
        $classes = new classes();
        $classes->updateTree_classes();
        clearCacheData();
    }

    function install($data = '')
    {
        parent::install();
        $this->getConfig();
        if (!is_array($this->config)) {
            $this->config = array();
        }
        if (!isset($this->config['installed']) || !is_array($this->config['installed'])) {
            $this->config['installed'] = array();
        }
        $this->saveConfig();
    }

    function uninstall()
    {
        // Registry only. Created demo entities are intentionally kept: removing
        // them requires an explicit "Uninstall selected" action on the module
        // page so that user edits are never lost by accident.
        parent::uninstall();
    }
}
