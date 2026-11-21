<?php
/**
 * Static validation for Starter Kit packages.
 *
 * Runs with PHP CLI only: no MajorDoMo installation and no database are
 * needed. It checks the package format, method/script syntax, onchange
 * targets, parent classes, {{TOKEN}} replacements and language dictionaries.
 *
 * Usage: php tests/package_validate.php
 */

$root = dirname(__DIR__);
$packagesDir = $root . '/modules/starter_kit/packages';
$langDir = $root . '/modules/starter_kit/lang';

$errors = array();
$warnings = array();
$packageIds = array();
$packageData = array();
$classTitles = array();
$objectTitles = array();
$scriptTitles = array();
$classParents = array();
$classMethods = array();
$objectClasses = array();
$objectPackages = array();
$sceneActions = array();
$sceneTitles = array();
$layoutRefs = array();
$layoutTitles = array();

// Standard classes shipped with the MajorDoMo schema. Package classes may
// inherit from them without defining them.
$coreClasses = array(
    'Timer', 'Computer', 'systemStates', 'OperationalModes', 'Rooms', 'Users',
    'SDevices', 'SControllers', 'SGroups', 'SRelays', 'SThermostats', 'SDimmers',
    'SRGB', 'SMotions', 'SCameras', 'SOpenClose', 'SOpenable', 'SLeak', 'SSmoke',
    'SCounters', 'SButtons', 'SSensors', 'SGeneralSensors', 'STempSensors',
    'SHumSensors', 'SStateSensors', 'SPercentageSensors', 'SPressureSensors',
    'SPowerSensors', 'SVoltageSensors', 'SCurrentSensors', 'SLightSensors', 'SHTML'
);

function validate_lint($code, $label, &$errors)
{
    $tmp = tempnam(sys_get_temp_dir(), 'sk_lint_');
    file_put_contents($tmp, "<?php\n" . $code);
    $output = array();
    $exitCode = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($tmp) . ' 2>&1', $output, $exitCode);
    unlink($tmp);
    if ($exitCode !== 0) {
        $errors[] = $label . ': syntax error: ' . implode(' ', $output);
    }
}

function validate_collect_tokens($value, &$tokens)
{
    if (is_array($value)) {
        foreach ($value as $item) {
            validate_collect_tokens($item, $tokens);
        }
    } elseif (is_string($value)) {
        if (preg_match_all('/\{\{([A-Z0-9_]+)\}\}/', $value, $matches)) {
            foreach ($matches[1] as $token) {
                $tokens[$token] = 1;
            }
        }
    }
}

function validate_entities($package, $type, &$errors, &$warnings, &$classTitles, &$objectTitles, &$scriptTitles,
                           &$classParents, &$classMethods, &$objectClasses, &$objectPackages, &$sceneActions, &$sceneTitles,
                           &$layoutRefs, &$layoutTitles)
{
    global $coreClasses;

    if (!isset($package[$type])) {
        return;
    }
    if (!is_array($package[$type])) {
        $errors[] = $package['id'] . ': "' . $type . '" must be an array';
        return;
    }

    if ($type == 'classes') {
        $known = array();
        foreach ($package[$type] as $entity) {
            $title = isset($entity['title']) ? $entity['title'] : '';
            if ($title == '') {
                $errors[] = $package['id'] . ': class without a title';
                continue;
            }
            if (isset($classTitles[$title])) {
                $errors[] = $package['id'] . ': duplicate class title ' . $title;
            }
            $classTitles[$title] = 1;

            $parent = isset($entity['parent']) ? $entity['parent'] : '';
            if ($parent != '' && !isset($known[$parent]) && !in_array($parent, $coreClasses, true)) {
                $errors[] = $package['id'] . ': class ' . $title . ' has unknown parent ' . $parent;
            }
            if ($parent != '' && in_array($parent, $coreClasses, true)) {
                // fine: standard class
            } elseif ($parent != '' && !isset($known[$parent])) {
                $warnings[] = $package['id'] . ': class ' . $title . ' is defined before its parent ' . $parent;
            }
            $known[$title] = $parent;

            $methods = isset($entity['methods']) && is_array($entity['methods']) ? $entity['methods'] : array();
            $classParents[$title] = $parent;
            $classMethods[$title] = array_keys($methods);
            foreach ($methods as $name => $code) {
                if (!is_string($code)) {
                    $errors[] = $package['id'] . ': class ' . $title . ' method ' . $name . ' must be a string';
                    continue;
                }
                validate_lint($code, $package['id'] . ': class ' . $title . '::' . $name, $errors);
            }

            $properties = isset($entity['properties']) && is_array($entity['properties']) ? $entity['properties'] : array();
            foreach ($properties as $name => $options) {
                if ($name == '') {
                    $errors[] = $package['id'] . ': class ' . $title . ' has a property without a name';
                    continue;
                }
                if (!is_array($options)) {
                    $errors[] = $package['id'] . ': class ' . $title . ' property ' . $name . ' options must be an array';
                    continue;
                }
                if (isset($options['onchange']) && $options['onchange'] != '') {
                    $target = $options['onchange'];
                    $found = false;
                    $cursor = $title;
                    while ($cursor != '') {
                        if (isset($known[$cursor])) {
                            $parentClass = $known[$cursor];
                            foreach ($package[$type] as $candidate) {
                                if ($candidate['title'] == $cursor && isset($candidate['methods'][$target])) {
                                    $found = true;
                                    break 2;
                                }
                            }
                            $cursor = $parentClass;
                        } else {
                            // Standard class parent: its methods are unknown here.
                            // Unknown parent classes are reported above.
                            $found = in_array($cursor, $coreClasses, true) ? null : false;
                            break;
                        }
                    }
                    if ($found === false) {
                        $errors[] = $package['id'] . ': class ' . $title . ' property ' . $name .
                            ' points to an unknown onchange method ' . $target;
                    }
                }
            }
        }
        return;
    }

    if ($type == 'objects') {
        foreach ($package[$type] as $entity) {
            $title = isset($entity['title']) ? $entity['title'] : '';
            if ($title == '') {
                $errors[] = $package['id'] . ': object without a title';
                continue;
            }
            if (isset($objectTitles[$title])) {
                $errors[] = $package['id'] . ': duplicate object title ' . $title;
            }
            $objectTitles[$title] = 1;

            $class = isset($entity['class']) ? $entity['class'] : '';
            if ($class == '') {
                $errors[] = $package['id'] . ': object ' . $title . ' has no class';
            } elseif (!isset($classTitles[$class]) && !in_array($class, $coreClasses, true)) {
                $errors[] = $package['id'] . ': object ' . $title . ' uses an unknown class ' . $class;
            }
            $objectClasses[$title] = $class;
            $objectPackages[$title] = $package['id'];

            $properties = isset($entity['properties']) && is_array($entity['properties']) ? $entity['properties'] : array();
            foreach ($properties as $name => $value) {
                if ($name == '') {
                    $errors[] = $package['id'] . ': object ' . $title . ' has a property without a name';
                }
                // Values for object-level properties are allowed, but a class
                // property is the intended and documented way.
                if (isset($classTitles[$class]) && !validate_class_has_property($package, $class, $name)) {
                    $warnings[] = $package['id'] . ': object ' . $title . ' sets property ' . $name .
                        ' which is not declared in class ' . $class;
                }
            }

            $methods = isset($entity['methods']) && is_array($entity['methods']) ? $entity['methods'] : array();
            foreach ($methods as $name => $code) {
                if (!is_string($code)) {
                    $errors[] = $package['id'] . ': object ' . $title . ' method ' . $name . ' must be a string';
                    continue;
                }
                validate_lint($code, $package['id'] . ': object ' . $title . '.' . $name, $errors);
            }
        }
        return;
    }

    if ($type == 'scripts') {
        foreach ($package[$type] as $entity) {
            $title = isset($entity['title']) ? $entity['title'] : '';
            if ($title == '') {
                $errors[] = $package['id'] . ': script without a title';
                continue;
            }
            if (isset($scriptTitles[$title])) {
                $errors[] = $package['id'] . ': duplicate script title ' . $title;
            }
            $scriptTitles[$title] = 1;

            if (!isset($entity['code']) || !is_string($entity['code'])) {
                $errors[] = $package['id'] . ': script ' . $title . ' has no code';
                continue;
            }
            validate_lint($entity['code'], $package['id'] . ': script ' . $title, $errors);
        }
        return;
    }

    if ($type == 'scenes') {
        foreach ($package[$type] as $entity) {
            $title = isset($entity['title']) ? $entity['title'] : '';
            if ($title == '') {
                $errors[] = $package['id'] . ': scene without a title';
                continue;
            }
            if (isset($sceneTitles[$title])) {
                $errors[] = $package['id'] . ': duplicate scene title ' . $title;
            }
            $sceneTitles[$title] = 1;

            $elements = isset($entity['elements']) && is_array($entity['elements']) ? $entity['elements'] : array();
            if (!count($elements)) {
                $warnings[] = $package['id'] . ': scene ' . $title . ' has no elements';
            }
            foreach ($elements as $element) {
                $elementTitle = isset($element['title']) ? $element['title'] : '';
                if ($elementTitle == '') {
                    $errors[] = $package['id'] . ': scene ' . $title . ' has an element without a title';
                }
                $actionObject = isset($element['action_object']) ? $element['action_object'] : '';
                $actionMethod = isset($element['action_method']) ? $element['action_method'] : '';
                if (($actionObject == '') !== ($actionMethod == '')) {
                    $errors[] = $package['id'] . ': scene ' . $title . ' element ' . $elementTitle .
                        ' must define both action_object and action_method';
                }
                if ($actionObject != '') {
                    $sceneActions[] = array(
                        'package' => $package['id'],
                        'scene' => $title,
                        'element' => $elementTitle,
                        'object' => $actionObject,
                        'method' => $actionMethod
                    );
                }
            }
        }
        return;
    }

    if ($type == 'layouts') {
        foreach ($package[$type] as $entity) {
            $title = isset($entity['title']) ? $entity['title'] : '';
            if ($title == '') {
                $errors[] = $package['id'] . ': layout without a title';
                continue;
            }
            if (isset($layoutTitles[$title])) {
                $errors[] = $package['id'] . ': duplicate layout title ' . $title;
            }
            $layoutTitles[$title] = 1;

            $layoutType = isset($entity['type']) ? $entity['type'] : 'html';
            if ($layoutType == 'html' && (!isset($entity['code']) || !is_string($entity['code']) || trim($entity['code']) == '')) {
                $errors[] = $package['id'] . ': layout ' . $title . ' has no code';
                continue;
            }
            if ($layoutType != 'html') {
                $warnings[] = $package['id'] . ': layout ' . $title . ' uses type ' . $layoutType .
                    '; only html pages are created with a cached file';
            }
            if (isset($entity['code']) && is_string($entity['code'])) {
                if (preg_match_all('/%([A-Za-z0-9_\-]+)\.([A-Za-z0-9_\-]+)(?:\|\d+)?%/', $entity['code'], $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $layoutRefs[] = array(
                            'package' => $package['id'],
                            'layout' => $title,
                            'object' => $match[1],
                            'property' => $match[2]
                        );
                    }
                }
            }
        }
    }
}

function validate_class_has_property($package, $classTitle, $propertyName)
{
    if (!isset($package['classes']) || !is_array($package['classes'])) {
        return false;
    }
    $parents = array();
    foreach ($package['classes'] as $candidate) {
        $parents[$candidate['title']] = isset($candidate['parent']) ? $candidate['parent'] : '';
        if ($candidate['title'] == $classTitle) {
            if (isset($candidate['properties'][$propertyName])) {
                return true;
            }
        }
    }
    $cursor = isset($parents[$classTitle]) ? $parents[$classTitle] : '';
    $guard = 0;
    while ($cursor != '' && $guard < 20) {
        foreach ($package['classes'] as $candidate) {
            if ($candidate['title'] == $cursor) {
                if (isset($candidate['properties'][$propertyName])) {
                    return true;
                }
            }
        }
        $cursor = isset($parents[$cursor]) ? $parents[$cursor] : '';
        $guard++;
    }
    return false;
}

$files = glob($packagesDir . '/*.php');
if (!is_array($files) || !count($files)) {
    fwrite(STDERR, "No packages found in " . $packagesDir . PHP_EOL);
    exit(1);
}
sort($files);

foreach ($files as $file) {
    $package = include($file);
    $label = basename($file);
    if (!is_array($package)) {
        $errors[] = $label . ': package must return an array';
        continue;
    }
    foreach (array('id', 'version', 'title_key', 'description_key') as $key) {
        if (!isset($package[$key]) || $package[$key] === '') {
            $errors[] = $label . ': missing "' . $key . '"';
        }
    }
    if (!isset($package['id'])) {
        continue;
    }
    if (isset($packageIds[$package['id']])) {
        $errors[] = $label . ': duplicate package id ' . $package['id'];
    }
    $packageIds[$package['id']] = 1;
    $packageData[$package['id']] = $package;

    $texts = isset($package['texts']) && is_array($package['texts']) ? $package['texts'] : array();
    foreach ($texts as $token => $langKey) {
        if ($token == '' || $langKey == '') {
            $errors[] = $package['id'] . ': empty text token or language key';
        }
    }

    $tokens = array();
    foreach (array('classes', 'objects', 'scripts', 'scenes', 'layouts') as $type) {
        if (isset($package[$type]) && is_array($package[$type])) {
            validate_collect_tokens($package[$type], $tokens);
        }
    }
    foreach (array_keys($tokens) as $token) {
        if (!isset($texts[$token])) {
            $errors[] = $package['id'] . ': token {{' . $token . '}} is not declared in "texts"';
        }
    }
    foreach (array_keys($texts) as $token) {
        if (!isset($tokens[$token])) {
            $warnings[] = $package['id'] . ': text token ' . $token . ' is declared but not used';
        }
    }

    foreach (array('classes', 'objects', 'scripts', 'scenes', 'layouts') as $type) {
        validate_entities($package, $type, $errors, $warnings, $classTitles, $objectTitles, $scriptTitles,
            $classParents, $classMethods, $objectClasses, $objectPackages, $sceneActions, $sceneTitles,
            $layoutRefs, $layoutTitles);
    }
}

// Dependencies: every required package must exist; cycles are not allowed.
foreach ($packageData as $id => $package) {
    if (!isset($package['requires']) || !is_array($package['requires'])) {
        continue;
    }
    foreach ($package['requires'] as $required_id) {
        if ($required_id == $id) {
            $errors[] = $id . ': package requires itself';
        } elseif (!isset($packageData[$required_id])) {
            $errors[] = $id . ': requires unknown package ' . $required_id;
        }
    }
}
$visiting = array();
$visited = array();
$visit = function ($id) use (&$visit, &$visiting, &$visited, &$packageData, &$errors) {
    if (isset($visited[$id])) {
        return;
    }
    if (isset($visiting[$id])) {
        $errors[] = $id . ': circular dependency between packages';
        return;
    }
    $visiting[$id] = 1;
    if (isset($packageData[$id]['requires']) && is_array($packageData[$id]['requires'])) {
        foreach ($packageData[$id]['requires'] as $required_id) {
            if (isset($packageData[$required_id])) {
                $visit($required_id);
            }
        }
    }
    unset($visiting[$id]);
    $visited[$id] = 1;
};
foreach (array_keys($packageData) as $id) {
    $visit($id);
}

// Transitive requirement closure for a package.
function package_requires($id, $packageData)
{
    $seen = array();
    $queue = array($id);
    while (count($queue)) {
        $current = array_shift($queue);
        if (isset($seen[$current])) {
            continue;
        }
        $seen[$current] = 1;
        if (isset($packageData[$current]['requires']) && is_array($packageData[$current]['requires'])) {
            foreach ($packageData[$current]['requires'] as $required_id) {
                if (!isset($seen[$required_id])) {
                    $queue[] = $required_id;
                }
            }
        }
    }
    unset($seen[$id]);
    return $seen;
}

// Scene buttons must call a method that exists on the target object (directly
// or through the class hierarchy), and the object must belong to the package
// itself or to one of its declared dependencies.
foreach ($sceneActions as $action) {
    if (!isset($objectClasses[$action['object']])) {
        $errors[] = $action['package'] . ': scene ' . $action['scene'] . ' element ' . $action['element'] .
            ' calls an unknown object ' . $action['object'];
        continue;
    }
    $owner = isset($objectPackages[$action['object']]) ? $objectPackages[$action['object']] : '';
    $allowed = package_requires($action['package'], $packageData);
    if ($owner != $action['package'] && !isset($allowed[$owner])) {
        $errors[] = $action['package'] . ': scene ' . $action['scene'] . ' element ' . $action['element'] .
            ' uses object ' . $action['object'] . ' from package ' . $owner . ' which is not in requires';
    }
    $class = $objectClasses[$action['object']];
    $found = false;
    $guard = 0;
    while ($class != '' && $guard < 30) {
        if (isset($classMethods[$class]) && in_array($action['method'], $classMethods[$class], true)) {
            $found = true;
            break;
        }
        if (!isset($classParents[$class])) {
            $found = null; // standard class: methods are unknown here
            break;
        }
        $class = $classParents[$class];
        $guard++;
    }
    if ($found === false) {
        $errors[] = $action['package'] . ': scene ' . $action['scene'] . ' element ' . $action['element'] .
            ' calls an unknown method ' . $action['object'] . '.' . $action['method'];
    }
}

// Layout placeholders %Object.Property% must point to objects from the package
// itself or from its declared dependencies.
foreach ($layoutRefs as $ref) {
    if (!isset($objectClasses[$ref['object']])) {
        $errors[] = $ref['package'] . ': layout ' . $ref['layout'] . ' uses unknown object ' .
            $ref['object'] . '.' . $ref['property'];
        continue;
    }
    $owner = isset($objectPackages[$ref['object']]) ? $objectPackages[$ref['object']] : '';
    $allowed = package_requires($ref['package'], $packageData);
    if ($owner != $ref['package'] && !isset($allowed[$owner])) {
        $errors[] = $ref['package'] . ': layout ' . $ref['layout'] . ' uses object ' . $ref['object'] .
            ' from package ' . $owner . ' which is not in requires';
    }
}

// Language dictionaries: default.php is the source of truth, ru.php is the
// primary translation kept in sync. Other languages may be partial and fall
// back to English at runtime, so a missing key is only a warning there.
$dictionaries = array();
foreach (glob($langDir . '/*.php') as $langFile) {
    $dictionary = include($langFile);
    $dictionaries[basename($langFile, '.php')] = is_array($dictionary) ? $dictionary : array();
}
if (isset($dictionaries['default'])) {
    foreach ($dictionaries as $lang => $dictionary) {
        if ($lang == 'default') {
            continue;
        }
        $missing = array_diff(array_keys($dictionaries['default']), array_keys($dictionary));
        $extra = array_diff(array_keys($dictionary), array_keys($dictionaries['default']));
        foreach ($missing as $key) {
            if ($lang == 'ru') {
                $errors[] = 'lang/' . $lang . '.php: missing key ' . $key;
            } else {
                $warnings[] = 'lang/' . $lang . '.php: missing key ' . $key . ' (falls back to default)';
            }
        }
        foreach ($extra as $key) {
            $errors[] = 'lang/' . $lang . '.php: key ' . $key . ' is not in default.php';
        }
    }
}

foreach ($packageData as $id => $package) {
    foreach (array('title_key', 'description_key') as $key) {
        if (!isset($package[$key])) {
            continue;
        }
        if (!isset($dictionaries['default'][$package[$key]])) {
            $errors[] = $id . ': ' . $key . ' ' . $package[$key] . ' is missing in lang/default.php';
        }
        if (isset($dictionaries['ru']) && !isset($dictionaries['ru'][$package[$key]])) {
            $errors[] = $id . ': ' . $key . ' ' . $package[$key] . ' is missing in lang/ru.php';
        }
    }
    if (isset($package['texts']) && is_array($package['texts'])) {
        foreach ($package['texts'] as $token => $langKey) {
            if (!isset($dictionaries['default'][$langKey])) {
                $errors[] = $id . ': text ' . $token . ' (' . $langKey . ') is missing in lang/default.php';
            }
            if (isset($dictionaries['ru']) && !isset($dictionaries['ru'][$langKey])) {
                $errors[] = $id . ': text ' . $token . ' (' . $langKey . ') is missing in lang/ru.php';
            }
        }
    }
}

foreach ($warnings as $warning) {
    echo 'WARN ' . $warning . PHP_EOL;
}
foreach ($errors as $error) {
    echo 'FAIL ' . $error . PHP_EOL;
}
if (count($errors)) {
    echo PHP_EOL . count($errors) . ' error(s), ' . count($warnings) . ' warning(s)' . PHP_EOL;
    exit(1);
}
echo 'OK: ' . count($packageIds) . ' package(s), ' . count($classTitles) . ' class(es), ' .
    count($objectTitles) . ' object(s), ' . count($scriptTitles) . ' script(s), ' .
    count($sceneTitles) . ' scene(s), ' . count($layoutTitles) . ' layout(s); ' .
    count($warnings) . ' warning(s)' . PHP_EOL;
