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

            foreach ($selected as $package_id) {
                if ($mode == 'install') {
                    $result = $this->installPackage($package_id);
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
        return $conflicts;
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
            'entities' => array('classes' => array(), 'objects' => array(), 'scripts' => array(), 'scenes' => array())
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

        $this->config['installed'][$package_id] = $record;
        $result['ok'] = true;
        $result['messages'][] = array(
            'CLASS' => 'success',
            'TEXT' => $this->lang($package['title_key'], $package_id) . ': ' . $this->lang('STARTER_KIT_INSTALL_DONE')
        );
        return $result;
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

        $record = array(
            'id' => (int)$class_id,
            'title' => $entity['title'],
            'properties' => array(),
            'methods' => array()
        );

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
     * Removes entities created during a failed install attempt.
     */
    function rollbackRecord($record)
    {
        $fake = array('entities' => $record['entities']);
        foreach (array('scripts', 'scenes', 'objects', 'classes') as $type) {
            if (!isset($fake['entities'][$type])) {
                continue;
            }
            foreach ($fake['entities'][$type] as $entity) {
                if ($type == 'scripts') {
                    SQLExec("DELETE FROM scripts WHERE ID=" . (int)$entity['id']);
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

        // Reverse order: scripts, scenes, objects, classes.
        foreach (array('scripts', 'scenes', 'objects', 'classes') as $type) {
            if (!isset($record['entities'][$type])) {
                continue;
            }
            foreach ($record['entities'][$type] as $title => $entity) {
                $status = 'removed';
                if ($type == 'scripts') {
                    $status = $this->removeScript($entity);
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
                    $skipped[] = $entity['title'];
                }
            }
        }

        $empty = true;
        foreach (array('scripts', 'scenes', 'objects', 'classes') as $type) {
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
