<?php
/**
 * Starter Kit package: scripts.
 *
 * Four everyday helpers with comments:
 * - a welcome message that can be spoken;
 * - an offline health check of the installation;
 * - presence simulation on the virtual lamp;
 * - a simple two-slot schedule (morning / evening).
 *
 * The helpers write their results to the StarterScripts object, so the effect
 * is visible without reading the log. Presence and schedule objects are Timer
 * subclasses and use the built-in onNewMinute/onNewDay hooks.
 *
 * Requires basic_entities (StarterLamp, StarterRoom).
 */

return array(
    'id' => 'scripts',
    'version' => 1,
    'requires' => array('basic_entities'),
    'title_key' => 'STARTER_KIT_PKG_SCRIPTS_TITLE',
    'description_key' => 'STARTER_KIT_PKG_SCRIPTS_DESC',
    'texts' => array(
        'SCRIPTS_DESC' => 'STARTER_KIT_TEXT_SCRIPTS_DESC',
        'SCRIPTS_OBJ_DESC' => 'STARTER_KIT_TEXT_SCRIPTS_OBJ_DESC',
        'PRESENCE_DESC' => 'STARTER_KIT_TEXT_PRESENCE_DESC',
        'PRESENCE_OBJ_DESC' => 'STARTER_KIT_TEXT_PRESENCE_OBJ_DESC',
        'SCHEDULE_DESC' => 'STARTER_KIT_TEXT_SCHEDULE_DESC',
        'SCHEDULE_OBJ_DESC' => 'STARTER_KIT_TEXT_SCHEDULE_OBJ_DESC',
        'GREETING_NIGHT' => 'STARTER_KIT_TEXT_GREETING_NIGHT',
        'GREETING_MORNING' => 'STARTER_KIT_TEXT_GREETING_MORNING',
        'GREETING_DAY' => 'STARTER_KIT_TEXT_GREETING_DAY',
        'GREETING_EVENING' => 'STARTER_KIT_TEXT_GREETING_EVENING',
        'ALL_OK' => 'STARTER_KIT_TEXT_ALL_OK',
        'WELCOME_DESC' => 'STARTER_KIT_TEXT_WELCOME_DESC',
        'CHECK_DESC' => 'STARTER_KIT_TEXT_CHECK_DESC',
        'PRESENCE_ON_DESC' => 'STARTER_KIT_TEXT_PRESENCE_ON_DESC',
        'PRESENCE_OFF_DESC' => 'STARTER_KIT_TEXT_PRESENCE_OFF_DESC',
        'SCHEDULE_MORNING_DESC' => 'STARTER_KIT_TEXT_SCHEDULE_MORNING_DESC',
        'SCHEDULE_EVENING_DESC' => 'STARTER_KIT_TEXT_SCHEDULE_EVENING_DESC'
    ),
    'classes' => array(
        array(
            'title' => 'SK_Scripts',
            'description' => '{{SCRIPTS_DESC}}',
            'properties' => array(
                'greeting' => array(),
                'sayEnabled' => array(),
                'connectionStatus' => array(),
                'connectionDetails' => array(),
                'checkedAt' => array(),
                'lastAction' => array()
            ),
            'methods' => array()
        ),
        array(
            'title' => 'SK_Presence',
            'parent' => 'Timer',
            'description' => '{{PRESENCE_DESC}}',
            'properties' => array(
                'enabled' => array(),
                'fromTime' => array(),
                'toTime' => array(),
                'chance' => array(),
                'togglesToday' => array(),
                'lastToggle' => array()
            ),
            'methods' => array(
                'onNewMinute' => <<<'CODE'
// Called every minute by cycle_main (SK_Presence inherits Timer).
$this->callMethod('simulate');
CODE
                ,
                'onNewDay' => <<<'CODE'
// Called once a day, right after midnight.
$this->setProperty('togglesToday', 0);
CODE
                ,
                'enable' => <<<'CODE'
// Used by the SK_PresenceOn script.
$this->setProperty('enabled', 1);
sg('StarterScripts.lastAction', 'presence: enabled');
CODE
                ,
                'disable' => <<<'CODE'
// Used by the SK_PresenceOff script.
$this->setProperty('enabled', 0);
sg('StarterScripts.lastAction', 'presence: disabled');
CODE
                ,
                'simulate' => <<<'CODE'
// Toggle the virtual lamp from time to time while the window is active.
// Chance is a percent per minute: 20 means roughly 12 toggles per hour.
if (!(int)$this->getProperty('enabled')) {
    return;
}

$now = date('H:i');
if (!$this->callMethod('inWindow', array('now' => $now))) {
    return;
}

$chance = (int)$this->getProperty('chance');
if ($chance <= 0) {
    $chance = 20;
}
if (mt_rand(1, 100) > $chance) {
    return;
}

$lampOn = (int)gg('StarterLamp.state');
if ($lampOn) {
    callMethod('StarterLamp.turnOff');
} else {
    callMethod('StarterLamp.setBrightness', array('level' => mt_rand(20, 90)));
}

$this->setProperty('lastToggle', date('Y-m-d H:i:s'));
$this->setProperty('togglesToday', (int)$this->getProperty('togglesToday') + 1);
sg('StarterScripts.lastAction', 'presence: lamp ' . ($lampOn ? 'off' : 'on') . ' at ' . date('H:i'));
CODE
                ,
                'inWindow' => <<<'CODE'
// Check whether the current time is inside fromTime..toTime.
// The window may cross midnight (for example 22:00..06:00).
$now = isset($params['now']) ? $params['now'] : date('H:i');
$from = trim((string)$this->getProperty('fromTime'));
$to = trim((string)$this->getProperty('toTime'));

if ($from === '' || $to === '') {
    return 1;
}
if ($from <= $to) {
    return ($now >= $from && $now < $to) ? 1 : 0;
}
return ($now >= $from || $now < $to) ? 1 : 0;
CODE
            )
        ),
        array(
            'title' => 'SK_Schedule',
            'parent' => 'Timer',
            'description' => '{{SCHEDULE_DESC}}',
            'properties' => array(
                'enabled' => array(),
                'morningTime' => array(),
                'eveningTime' => array(),
                'morningLevel' => array(),
                'lastSlot' => array()
            ),
            'methods' => array(
                'onNewMinute' => <<<'CODE'
// Called every minute by cycle_main (SK_Schedule inherits Timer).
// timeIs() is a built-in helper: it is true during the matching minute.
if (!(int)$this->getProperty('enabled')) {
    return;
}
if (timeIs($this->getProperty('morningTime'))) {
    $this->callMethod('runSlot', array('slot' => 'morning'));
}
if (timeIs($this->getProperty('eveningTime'))) {
    $this->callMethod('runSlot', array('slot' => 'evening'));
}
CODE
                ,
                'runSlot' => <<<'CODE'
// Run one scheduled slot. The same method is called by the
// SK_ScheduleMorning and SK_ScheduleEvening scripts for a quick test.
$slot = isset($params['slot']) ? $params['slot'] : '';

if ($slot == 'morning') {
    // Wake-up: warm light for the first part of the day.
    callMethod('StarterLamp.setBrightness', array('level' => (int)$this->getProperty('morningLevel')));
} elseif ($slot == 'evening') {
    // Evening: lights off.
    callMethod('StarterLamp.turnOff');
} else {
    return;
}

$this->setProperty('lastSlot', $slot . ' ' . date('Y-m-d H:i:s'));
sg('StarterScripts.lastAction', 'schedule: ' . $slot . ' at ' . date('H:i'));
callMethod('StarterRoom.refresh');
CODE
            )
        )
    ),
    'objects' => array(
        array(
            'title' => 'StarterScripts',
            'class' => 'SK_Scripts',
            'description' => '{{SCRIPTS_OBJ_DESC}}',
            'properties' => array(
                'sayEnabled' => 0,
                'connectionStatus' => 'unknown'
            )
        ),
        array(
            'title' => 'StarterPresence',
            'class' => 'SK_Presence',
            'description' => '{{PRESENCE_OBJ_DESC}}',
            'properties' => array(
                'enabled' => 0,
                'fromTime' => '19:00',
                'toTime' => '23:00',
                'chance' => 20,
                'togglesToday' => 0
            )
        ),
        array(
            'title' => 'StarterSchedule',
            'class' => 'SK_Schedule',
            'description' => '{{SCHEDULE_OBJ_DESC}}',
            'properties' => array(
                'enabled' => 0,
                'morningTime' => '08:00',
                'eveningTime' => '23:00',
                'morningLevel' => 60
            )
        )
    ),
    'scripts' => array(
        array(
            'title' => 'SK_Welcome',
            'description' => '{{WELCOME_DESC}}',
            'code' => <<<'CODE'
// Starter Kit: a time-based welcome message.
// The text is always saved to StarterScripts.greeting; speech is optional
// and controlled by StarterScripts.sayEnabled (0 by default).

$hour = (int)date('G');
if ($hour < 5) {
    $greeting = '{{GREETING_NIGHT}}';
} elseif ($hour < 12) {
    $greeting = '{{GREETING_MORNING}}';
} elseif ($hour < 18) {
    $greeting = '{{GREETING_DAY}}';
} else {
    $greeting = '{{GREETING_EVENING}}';
}

sg('StarterScripts.greeting', $greeting);
sg('StarterScripts.lastAction', 'welcome: ' . $greeting);

if ((int)gg('StarterScripts.sayEnabled')) {
    say($greeting);
}
DebMes('StarterKit welcome: ' . $greeting, 'starter_kit');
CODE
        ),
        array(
            'title' => 'SK_ConnectionCheck',
            'description' => '{{CHECK_DESC}}',
            'code' => <<<'CODE'
// Starter Kit: an offline health check of the installation.
// It does not use the internet: only the database, config.php and the
// timestamp of the main cycle are checked.

$status = 'ok';
$details = array();

try {
    $row = SQLSelectOne('SELECT NOW() AS DB_TIME');
    if (empty($row['DB_TIME'])) {
        $status = 'error';
        $details[] = 'database';
    }
} catch (Throwable $e) {
    $status = 'error';
    $details[] = 'database';
}

$lastMain = (int)gg('ThisComputer.cycle_mainRun');
if (!$lastMain || (time() - $lastMain) > 5 * 60) {
    if ($status == 'ok') {
        $status = 'warning';
    }
    $details[] = 'cycle_main';
}

if (!file_exists(ROOT . 'config.php')) {
    $status = 'error';
    $details[] = 'config';
}

sg('StarterScripts.connectionStatus', $status);
sg('StarterScripts.connectionDetails', count($details) ? implode(', ', $details) : '{{ALL_OK}}');
sg('StarterScripts.checkedAt', date('Y-m-d H:i:s'));
sg('StarterScripts.lastAction', 'connection: ' . $status);
DebMes('StarterKit connection check: ' . $status . ' ' . implode(', ', $details), 'starter_kit');
CODE
        ),
        array(
            'title' => 'SK_PresenceOn',
            'description' => '{{PRESENCE_ON_DESC}}',
            'code' => <<<'CODE'
// Starter Kit: enable presence simulation.
callMethod('StarterPresence.enable');
DebMes('StarterKit presence simulation enabled', 'starter_kit');
CODE
        ),
        array(
            'title' => 'SK_PresenceOff',
            'description' => '{{PRESENCE_OFF_DESC}}',
            'code' => <<<'CODE'
// Starter Kit: disable presence simulation.
callMethod('StarterPresence.disable');
DebMes('StarterKit presence simulation disabled', 'starter_kit');
CODE
        ),
        array(
            'title' => 'SK_ScheduleMorning',
            'description' => '{{SCHEDULE_MORNING_DESC}}',
            'code' => <<<'CODE'
// Starter Kit: run the morning slot now, without waiting for the clock.
callMethod('StarterSchedule.runSlot', array('slot' => 'morning'));
CODE
        ),
        array(
            'title' => 'SK_ScheduleEvening',
            'description' => '{{SCHEDULE_EVENING_DESC}}',
            'code' => <<<'CODE'
// Starter Kit: run the evening slot now, without waiting for the clock.
callMethod('StarterSchedule.runSlot', array('slot' => 'evening'));
CODE
        )
    )
);
