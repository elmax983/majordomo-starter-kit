<?php
/**
 * Starter Kit package: working modes.
 *
 * Creates regular OperationalModes objects (the standard "Working modes"
 * section) and a small summary hub. Each mode has an object-level modeChanged
 * handler, so activation updates timestamps and the summary without speech.
 *
 * Modes:
 * - Debug: only marks itself active and writes to the log.
 * - Guest: lights off, comfortable heating.
 * - Vacation: lights off, heating off.
 *
 * Requires basic_entities (lamp, thermostat).
 */

return array(
    'id' => 'modes',
    'version' => 1,
    'requires' => array('basic_entities'),
    'title_key' => 'STARTER_KIT_PKG_MODES_TITLE',
    'description_key' => 'STARTER_KIT_PKG_MODES_DESC',
    'hints' => array(
        'shows' => 'STARTER_KIT_HINT_MODES_SHOWS',
        'adapt' => 'STARTER_KIT_HINT_MODES_ADAPT'
    ),
    'texts' => array(
        'MODES_DESC' => 'STARTER_KIT_TEXT_MODES_DESC',
        'MODES_OBJ_DESC' => 'STARTER_KIT_TEXT_MODES_OBJ_DESC',
        'MODE_DEBUG' => 'STARTER_KIT_TEXT_MODE_DEBUG',
        'MODE_GUEST' => 'STARTER_KIT_TEXT_MODE_GUEST',
        'MODE_VACATION' => 'STARTER_KIT_TEXT_MODE_VACATION',
        'MODE_NONE' => 'STARTER_KIT_TEXT_MODE_NONE',
        'MODE_DEBUG_OBJ' => 'STARTER_KIT_TEXT_MODE_DEBUG_OBJ',
        'MODE_GUEST_OBJ' => 'STARTER_KIT_TEXT_MODE_GUEST_OBJ',
        'MODE_VACATION_OBJ' => 'STARTER_KIT_TEXT_MODE_VACATION_OBJ',
        'MODE_DEBUG_SCRIPT' => 'STARTER_KIT_TEXT_MODE_DEBUG_SCRIPT',
        'MODE_GUEST_SCRIPT' => 'STARTER_KIT_TEXT_MODE_GUEST_SCRIPT',
        'MODE_VACATION_SCRIPT' => 'STARTER_KIT_TEXT_MODE_VACATION_SCRIPT',
        'MODES_OFF_SCRIPT' => 'STARTER_KIT_TEXT_MODES_OFF_SCRIPT'
    ),
    'classes' => array(
        array(
            'title' => 'SK_Modes',
            'description' => '{{MODES_DESC}}',
            'template' => '<b>%.object_title%</b>: %.summary%',
            'properties' => array(
                'debug' => array(),
                'guest' => array(),
                'vacation' => array(),
                'summary' => array(),
                'updated' => array()
            ),
            'methods' => array(
                'sync' => <<<'CODE'
// Refresh the summary from the three standard mode objects.
$debug = (int)gg('DebugMode.active');
$guest = (int)gg('GuestMode.active');
$vacation = (int)gg('VacationMode.active');

$this->setProperty('debug', $debug);
$this->setProperty('guest', $guest);
$this->setProperty('vacation', $vacation);

$parts = array();
if ($debug) {
    $parts[] = '{{MODE_DEBUG}}';
}
if ($guest) {
    $parts[] = '{{MODE_GUEST}}';
}
if ($vacation) {
    $parts[] = '{{MODE_VACATION}}';
}
$summary = count($parts) ? implode(', ', $parts) : '{{MODE_NONE}}';
$this->setProperty('summary', $summary);
$this->setProperty('updated', date('Y-m-d H:i:s'));
DebMes('StarterKit modes: ' . $summary, 'starter_kit');
CODE
                ,
                'setMode' => <<<'CODE'
// Usage: callMethod('StarterModes.setMode', array('mode' => 'debug', 'active' => 1));
// Setting "active" fires the standard property onchange (modeChanged).
$mode = isset($params['mode']) ? strtolower(trim($params['mode'])) : '';
$active = isset($params['active']) ? (int)$params['active'] : 1;
$map = array(
    'debug' => 'DebugMode',
    'guest' => 'GuestMode',
    'vacation' => 'VacationMode'
);
if (!isset($map[$mode])) {
    return 0;
}
sg($map[$mode] . '.active', $active);
$this->callMethod('sync');
return 1;
CODE
                ,
                'allOff' => <<<'CODE'
// Deactivate every Starter Kit mode.
foreach (array('DebugMode', 'GuestMode', 'VacationMode') as $title) {
    sg($title . '.active', 0);
}
$this->callMethod('sync');
CODE
                ,
                'report' => <<<'CODE'
// Short human readable summary, handy for scripts and the panel.
return $this->getProperty('summary');
CODE
            )
        )
    ),
    'objects' => array(
        array(
            'title' => 'StarterModes',
            'class' => 'SK_Modes',
            'description' => '{{MODES_OBJ_DESC}}',
            'properties' => array(
                'summary' => '{{MODE_NONE}}'
            )
        ),
        array(
            'title' => 'DebugMode',
            'class' => 'OperationalModes',
            'description' => '{{MODE_DEBUG_OBJ}}',
            'properties' => array(
                'title' => '{{MODE_DEBUG}}',
                'active' => 0
            ),
            'methods' => array(
                'modeChanged' => <<<'CODE'
// Object-level override of the stock OperationalModes handler:
// keep timestamps and refresh the Starter Kit summary, no speech.
$this->setProperty('updated', time());
$this->setProperty('updatedTime', date('H:i'));
if ((int)$this->getProperty('active')) {
    DebMes('StarterKit debug mode enabled', 'starter_kit');
} else {
    DebMes('StarterKit debug mode disabled', 'starter_kit');
}
callMethod('StarterModes.sync');
CODE
            )
        ),
        array(
            'title' => 'GuestMode',
            'class' => 'OperationalModes',
            'description' => '{{MODE_GUEST_OBJ}}',
            'properties' => array(
                'title' => '{{MODE_GUEST}}',
                'active' => 0
            ),
            'methods' => array(
                'modeChanged' => <<<'CODE'
// Guest mode: lights off, comfortable heating while somebody is visiting.
$this->setProperty('updated', time());
$this->setProperty('updatedTime', date('H:i'));
if ((int)$this->getProperty('active')) {
    callMethod('StarterLamp.turnOff');
    sg('StarterThermostat.mode', 'heat');
    sg('StarterThermostat.targetTemp', 21);
    DebMes('StarterKit guest mode enabled', 'starter_kit');
}
callMethod('StarterModes.sync');
CODE
            )
        ),
        array(
            'title' => 'VacationMode',
            'class' => 'OperationalModes',
            'description' => '{{MODE_VACATION_OBJ}}',
            'properties' => array(
                'title' => '{{MODE_VACATION}}',
                'active' => 0
            ),
            'methods' => array(
                'modeChanged' => <<<'CODE'
// Vacation mode: lights off and heating off while nobody is home.
$this->setProperty('updated', time());
$this->setProperty('updatedTime', date('H:i'));
if ((int)$this->getProperty('active')) {
    callMethod('StarterLamp.turnOff');
    sg('StarterThermostat.mode', 'off');
    DebMes('StarterKit vacation mode enabled', 'starter_kit');
}
callMethod('StarterModes.sync');
CODE
            )
        )
    ),
    'scripts' => array(
        array(
            'title' => 'SK_ModeDebug',
            'description' => '{{MODE_DEBUG_SCRIPT}}',
            'code' => <<<'CODE'
// Toggle Debug mode.
$active = (int)gg('DebugMode.active');
callMethod('StarterModes.setMode', array('mode' => 'debug', 'active' => $active ? 0 : 1));
CODE
        ),
        array(
            'title' => 'SK_ModeGuest',
            'description' => '{{MODE_GUEST_SCRIPT}}',
            'code' => <<<'CODE'
// Toggle Guest mode.
$active = (int)gg('GuestMode.active');
callMethod('StarterModes.setMode', array('mode' => 'guest', 'active' => $active ? 0 : 1));
CODE
        ),
        array(
            'title' => 'SK_ModeVacation',
            'description' => '{{MODE_VACATION_SCRIPT}}',
            'code' => <<<'CODE'
// Toggle Vacation mode.
$active = (int)gg('VacationMode.active');
callMethod('StarterModes.setMode', array('mode' => 'vacation', 'active' => $active ? 0 : 1));
CODE
        ),
        array(
            'title' => 'SK_ModesOff',
            'description' => '{{MODES_OFF_SCRIPT}}',
            'code' => <<<'CODE'
// Deactivate all Starter Kit modes.
callMethod('StarterModes.allOff');
CODE
        )
    )
);
