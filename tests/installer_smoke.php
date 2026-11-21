<?php
/**
 * Installer smoke test for the starter_kit module.
 *
 * Exercises createScene/removeScene and createLayout/removeLayout with
 * in-memory SQL stubs and a temporary ROOT directory. It does not need a
 * MajorDoMo installation or a database, but it also does not validate the
 * real schema: run a full install on a test stand before release.
 *
 * Usage: php tests/installer_smoke.php
 */

$errors = array();
$db = array();
$nextId = array();

class module
{
}

function fail_test($message)
{
    global $errors;
    $errors[] = $message;
}

function check($condition, $message)
{
    if (!$condition) {
        fail_test($message);
    }
}

function DBSafe($value)
{
    return str_replace("'", "''", (string)$value);
}

function table_rows($table)
{
    global $db;
    return isset($db[$table]) ? $db[$table] : array();
}

function SQLInsert($table, $record)
{
    global $db, $nextId;
    if (!isset($nextId[$table])) {
        $nextId[$table] = 0;
    }
    $nextId[$table]++;
    $record['ID'] = $nextId[$table];
    $db[$table][$record['ID']] = $record;
    return $record['ID'];
}

function SQLSelectOne($query)
{
    $rows = fake_select($query);
    return count($rows) ? $rows[0] : array();
}

function SQLSelect($query)
{
    return fake_select($query);
}

function fake_select($query)
{
    global $db;
    if (preg_match('/^\s*SELECT\s+([\w\s,*]+?)\s+FROM\s+(\w+)\s+WHERE\s+(\w+)\s*=\s*(\d+)\s*$/i', $query, $m)) {
        $table = $m[2];
        $column = strtoupper($m[3]);
        $value = (int)$m[4];
        $result = array();
        if (isset($db[$table])) {
            foreach ($db[$table] as $row) {
                if (isset($row[$column]) && (int)$row[$column] === $value) {
                    $result[] = $row;
                }
            }
        }
        return $result;
    }
    fail_test('unsupported SELECT in smoke test: ' . $query);
    return array();
}

function SQLExec($query)
{
    global $db;
    if (preg_match('/^\s*DELETE\s+FROM\s+(\w+)\s+WHERE\s+(\w+)\s*=\s*(\d+)\s*$/i', $query, $m)) {
        $table = $m[1];
        $column = strtoupper($m[2]);
        $value = (int)$m[3];
        if (isset($db[$table])) {
            foreach ($db[$table] as $id => $row) {
                if (isset($row[$column]) && (int)$row[$column] === $value) {
                    unset($db[$table][$id]);
                }
            }
        }
        return true;
    }
    fail_test('unsupported DELETE in smoke test: ' . $query);
    return false;
}

$root = sys_get_temp_dir() . '/sk_smoke_' . getmypid();
@mkdir($root . '/cms/layouts', 0755, true);
define('ROOT', $root . '/');
define('LANG_STARTER_TEST_NAME', 'Hello');

require dirname(__DIR__) . '/modules/starter_kit/starter_kit.class.php';

$reflection = new ReflectionClass('starter_kit');
$kit = $reflection->newInstanceWithoutConstructor();
$kit->texts = array('NAME' => 'STARTER_TEST_NAME');

// --- dependency ordering -------------------------------------------------
$kit->packages = array(
    'base' => array('id' => 'base'),
    'middle' => array('id' => 'middle', 'requires' => array('base')),
    'top' => array('id' => 'top', 'requires' => array('middle')),
    'other' => array('id' => 'other')
);
$order = $kit->sortByRequirements(array('top', 'middle', 'base', 'other'));
check($order === array('base', 'middle', 'top', 'other'), 'dependencies should be installed first, got: ' . implode(',', $order));
$order = $kit->sortByRequirements(array('middle', 'base'));
check($order === array('base', 'middle'), 'partial selection should stay ordered');
$order = array_reverse($kit->sortByRequirements(array('base', 'middle', 'top')));
check($order === array('top', 'middle', 'base'), 'uninstall should remove dependents first');

// --- scene: create -------------------------------------------------------
$scene_record = $kit->createScene(array(
    'title' => '{{NAME}} scene',
    'priority' => 10,
    'elements' => array(
        array(
            'title' => '{{NAME}} button',
            'type' => 'button',
            'top' => 100,
            'left' => 120,
            'width' => 240,
            'height' => 60,
            'action_object' => 'StarterScenes',
            'action_method' => 'goodMorning'
        )
    )
));
check(is_array($scene_record), 'createScene should return a record');
check($scene_record['title'] === 'Hello scene', 'scene title should resolve {{NAME}}');
check(count(table_rows('scenes')) === 1, 'one scene row expected');
check(count(table_rows('elements')) === 1, 'one element row expected');
check(count(table_rows('elm_states')) === 1, 'one element state row expected');
$state = SQLSelectOne('SELECT * FROM elm_states WHERE ID=' . (int)$scene_record['elements'][0]['state_id']);
check($state['ACTION_OBJECT'] === 'StarterScenes', 'action object stored');
check($state['ACTION_METHOD'] === 'goodMorning', 'action method stored');
check($state['HTML'] === 'Hello button', 'button label stored');

// --- scene: uninstall removes everything ---------------------------------
$status = $kit->removeScene($scene_record);
check($status === 'removed', 'unchanged scene should be removed');
check(count(table_rows('scenes')) === 0, 'scene row removed');
check(count(table_rows('elements')) === 0, 'element rows removed');
check(count(table_rows('elm_states')) === 0, 'state rows removed');

// --- scene: modified element is kept -------------------------------------
$scene_record = $kit->createScene(array(
    'title' => 'Hello scene 2',
    'elements' => array(array('title' => 'Go', 'type' => 'button', 'action_object' => 'StarterScenes', 'action_method' => 'away'))
));
$state = SQLSelectOne('SELECT * FROM elm_states WHERE ID=' . (int)$scene_record['elements'][0]['state_id']);
$state['HTML'] = 'changed by user';
$GLOBALS['db']['elm_states'][$state['ID']] = $state;
$status = $kit->removeScene($scene_record);
check($status === 'skipped', 'modified scene should be skipped');
check(count(table_rows('scenes')) === 1, 'modified scene row kept');
check(count(table_rows('elements')) === 1, 'modified element kept');

// --- text file: create and safe removal ----------------------------------
$text_record = $kit->createTextFile(array(
    'name' => 'smoke_note',
    'content' => 'Hello {{NAME}}'
));
check(is_array($text_record), 'createTextFile should return a record');
check(is_file(ROOT . $text_record['file']), 'text file should be written');
check(file_get_contents(ROOT . $text_record['file']) === 'Hello Hello', 'text file tokens resolved');
$status = $kit->removeTextFile($text_record);
check($status === 'removed', 'unchanged text file should be removed');
check(!is_file(ROOT . $text_record['file']), 'text file removed');

$text_record = $kit->createTextFile(array('name' => 'smoke_note2', 'content' => 'original'));
file_put_contents(ROOT . $text_record['file'], 'edited');
$status = $kit->removeTextFile($text_record);
check($status === 'skipped', 'edited text file should be skipped');
check(is_file(ROOT . $text_record['file']), 'edited text file kept');
@unlink(ROOT . $text_record['file']);

// --- layout: create ------------------------------------------------------
$layout_record = $kit->createLayout(array(
    'title' => '{{NAME}} page',
    'type' => 'html',
    'priority' => 50,
    'code' => '<div>%StarterLamp.status|10%</div>'
));
check(is_array($layout_record), 'createLayout should return a record');
check($layout_record['file'] === 'cms/layouts/' . $layout_record['id'] . '.html', 'layout file path');
check(is_file(ROOT . $layout_record['file']), 'layout file should be written');
check(file_get_contents(ROOT . $layout_record['file']) === '<div>%StarterLamp.status|10%</div>', 'layout file content');
check(count(table_rows('layouts')) === 1, 'one layout row expected');

// --- layout: uninstall removes row and file ------------------------------
$status = $kit->removeLayout($layout_record);
check($status === 'removed', 'unchanged layout should be removed');
check(count(table_rows('layouts')) === 0, 'layout row removed');
check(!is_file(ROOT . $layout_record['file']), 'layout file removed');

// --- layout: edited file is kept -----------------------------------------
$layout_record = $kit->createLayout(array(
    'title' => 'Hello page 2',
    'type' => 'html',
    'code' => '<div>test</div>'
));
file_put_contents(ROOT . $layout_record['file'], '<div>edited</div>');
$status = $kit->removeLayout($layout_record);
check($status === 'skipped', 'edited layout should be skipped');
check(count(table_rows('layouts')) === 1, 'edited layout row kept');
check(is_file(ROOT . $layout_record['file']), 'edited layout file kept');

// cleanup
foreach (table_rows('layouts') as $row) {
    @unlink(ROOT . 'cms/layouts/' . (int)$row['ID'] . '.html');
}
@rmdir($root . '/cms/texts');
@rmdir($root . '/cms/layouts');
@rmdir($root . '/cms');
@rmdir($root);

if (count($errors)) {
    foreach ($errors as $error) {
        echo 'FAIL ' . $error . PHP_EOL;
    }
    echo count($errors) . ' error(s)' . PHP_EOL;
    exit(1);
}
echo 'OK: scene and layout create/remove smoke test passed' . PHP_EOL;
