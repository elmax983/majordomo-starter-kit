<?php
/**
 * Starter Kit package: clock and chime demo.
 *
 * Demonstrates the built-in object hooks: onNewMinute, onNewHour, onNewDay.
 * No external services and no real hardware are used.
 */

return array(
    'id' => 'clock_chime',
    'version' => 1,
    'title_key' => 'STARTER_KIT_PKG_CLOCK_TITLE',
    'description_key' => 'STARTER_KIT_PKG_CLOCK_DESC',
    'classes' => array(
        array(
            'title' => 'SK_Clock',
            'parent' => 'Timer',
            'description' => 'Starter Kit: time and chime demo',
            'properties' => array(
                'enabled' => array(),
                'sayChime' => array(),
                'quietFrom' => array(),
                'quietTo' => array(),
                'currentTime' => array(),
                'lastChime' => array(),
                'chimesToday' => array(),
                'dayStarted' => array()
            ),
            'methods' => array()
        )
    ),
    'objects' => array(
        array(
            'title' => 'StarterClock',
            'class' => 'SK_Clock',
            'description' => 'Starter Kit: updates the current time and counts chimes',
            'properties' => array(
                'enabled' => 1,
                'sayChime' => 0,
                'quietFrom' => '22:00',
                'quietTo' => '07:00',
                'chimesToday' => 0
            ),
            'methods' => array(
                'onNewMinute' => <<<'CODE'
// Runs every minute while cycle_main is alive.
sg('StarterClock.currentTime', date('H:i'));
CODE
                ,
                'onNewHour' => <<<'CODE'
// Runs at the start of every hour.
$enabled = (int)gg('StarterClock.enabled');
$sayChime = (int)gg('StarterClock.sayChime');
$now = date('H:i');
$quietFrom = gg('StarterClock.quietFrom');
$quietTo = gg('StarterClock.quietTo');

$quiet = false;
if ($quietFrom != '' && $quietTo != '') {
    if ($quietFrom <= $quietTo) {
        $quiet = ($now >= $quietFrom && $now < $quietTo);
    } else {
        $quiet = ($now >= $quietFrom || $now < $quietTo);
    }
}

if ($enabled && !$quiet) {
    sg('StarterClock.lastChime', date('Y-m-d H:i'));
    sg('StarterClock.chimesToday', (int)gg('StarterClock.chimesToday') + 1);
    if ($sayChime) {
        say('Chime: ' . date('H:i'));
    }
}
CODE
                ,
                'onNewDay' => <<<'CODE'
// Runs once a day, right after midnight.
sg('StarterClock.chimesToday', 0);
sg('StarterClock.dayStarted', date('Y-m-d'));
CODE
            )
        )
    )
);
