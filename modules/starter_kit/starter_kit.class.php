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
            $installed = isset($this->config['installed'][$id]);
            $record = $installed ? $this->config['installed'][$id] : array();
            $conflicts = $installed ? array() : $this->detectConflicts($package);
            $note = '';
            if (count($conflicts)) {
                $note = $this->lang('STARTER_KIT_CONFLICT') . ': ' . implode(', ', $conflicts);
            }
            $result[] = array(
                'ID' => $id,
                'TITLE' => $this->lang($package['title_key'], $id),
                'DESCRIPTION' => $this->lang($package['description_key'], ''),
                'INSTALLED' => $installed ? 1 : 0,
                'INSTALLED_DATE' => isset($record['installed_at']) ? $record['installed_at'] : '',
                'NOTE' => $note,
            );
        }
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
                $rec = SQLSelectOne("SELECT ID FROM " . $table . " WHERE TITLE='" . DBSafe($entity['title']) . "'");
                if ($rec['ID']) {
                    $conflicts[] = $entity['title'];
                }
            }
        }
        if (isset($package['scripts']) && is_array($package['scripts'])) {
            foreach ($package['scripts'] as $entity) {
                $rec = SQLSelectOne("SELECT ID FROM scripts WHERE TITLE='" . DBSafe($entity['title']) . "'");
                if ($rec['ID']) {
                    $conflicts[] = $entity['title'];
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
            'entities' => array('classes' => array(), 'objects' => array(), 'scripts' => array())
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
            $class_rec['DESCRIPTION'] = $entity['description'];
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
                $keep_history = isset($options['keep_history']) ? (int)$options['keep_history'] : 0;
                $property_id = addClassProperty($entity['title'], $name, $keep_history);
                if ($property_id) {
                    $record['properties'][$name] = (int)$property_id;
                }
            }
        }

        if (isset($entity['methods']) && is_array($entity['methods'])) {
            foreach ($entity['methods'] as $name => $code) {
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
            $object_rec['DESCRIPTION'] = $entity['description'];
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
                setGlobal($entity['title'] . '.' . $name, $value);
            }
        }

        if (isset($entity['methods']) && is_array($entity['methods'])) {
            foreach ($entity['methods'] as $name => $code) {
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
        $script = array(
            'TITLE' => $entity['title'],
            'CODE' => $entity['code'],
            'DESCRIPTION' => isset($entity['description']) ? $entity['description'] : '',
            'TYPE' => 0
        );
        $script['ID'] = SQLInsert('scripts', $script);
        return array(
            'id' => (int)$script['ID'],
            'title' => $entity['title'],
            'hash' => md5(trim($entity['code']))
        );
    }

    /**
     * Removes entities created during a failed install attempt.
     */
    function rollbackRecord($record)
    {
        $fake = array('entities' => $record['entities']);
        foreach (array('scripts', 'objects', 'classes') as $type) {
            if (!isset($fake['entities'][$type])) {
                continue;
            }
            foreach ($fake['entities'][$type] as $entity) {
                if ($type == 'scripts') {
                    SQLExec("DELETE FROM scripts WHERE ID=" . (int)$entity['id']);
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
        $skipped = array();

        // Reverse order: scripts, objects, classes.
        foreach (array('scripts', 'objects', 'classes') as $type) {
            if (!isset($record['entities'][$type])) {
                continue;
            }
            foreach ($record['entities'][$type] as $title => $entity) {
                $status = 'removed';
                if ($type == 'scripts') {
                    $status = $this->removeScript($entity);
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
        foreach (array('scripts', 'objects', 'classes') as $type) {
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
        $properties = SQLSelect("SELECT ID, TITLE FROM properties WHERE CLASS_ID=" . (int)$entity['id'] . " AND OBJECT_ID=0");
        foreach ($properties as $property) {
            if (!isset($entity['properties'][$property['TITLE']])) {
                return 'skipped'; // user added a class property
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
