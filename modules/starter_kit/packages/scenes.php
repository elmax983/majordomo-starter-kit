<?php
/**
 * Starter Kit package: scenes.
 *
 * Adds four ready scenes (Good morning, Good night, Away, Home) on the virtual
 * devices from the "basic_entities" package. Every scene is a regular scenes
 * module page with one button; the button calls a method of the StarterScenes
 * object, so all action logic stays visible and editable in the object editor.
 *
 * No hardware, no MQTT, no external services.
 */

return array(
    'id' => 'scenes',
    'version' => 1,
    'requires' => array('basic_entities'),
    'title_key' => 'STARTER_KIT_PKG_SCENES_TITLE',
    'description_key' => 'STARTER_KIT_PKG_SCENES_DESC',
    'texts' => array(
        'SCENE_DESC' => 'STARTER_KIT_TEXT_SCENE_DESC',
        'SCENE_OBJ_DESC' => 'STARTER_KIT_TEXT_SCENE_OBJ_DESC',
        'SCENE_MORNING' => 'STARTER_KIT_TEXT_SCENE_MORNING',
        'SCENE_NIGHT' => 'STARTER_KIT_TEXT_SCENE_NIGHT',
        'SCENE_AWAY' => 'STARTER_KIT_TEXT_SCENE_AWAY',
        'SCENE_HOME' => 'STARTER_KIT_TEXT_SCENE_HOME'
    ),
    'classes' => array(
        array(
            'title' => 'SK_Scene',
            'description' => '{{SCENE_DESC}}',
            'properties' => array(
                'activeScene' => array(),
                'presence' => array(),
                'lastActivated' => array(),
                'morningLevel' => array(),
                'homeLevel' => array(),
                'dayTarget' => array(),
                'nightTarget' => array()
            ),
            'methods' => array(
                'goodMorning' => <<<'CODE'
// Scene "Good morning": turn the lamp on and warm the room up.
// Levels and targets are properties, so they can be changed without code.
callMethod('StarterLamp.setBrightness', array('level' => (int)$this->getProperty('morningLevel')));
sg('StarterThermostat.mode', 'heat');
sg('StarterThermostat.targetTemp', (float)$this->getProperty('dayTarget'));

$this->setProperty('presence', 1);
$this->setProperty('activeScene', 'morning');
$this->setProperty('lastActivated', date('Y-m-d H:i:s'));
callMethod('StarterRoom.refresh');
CODE
                ,
                'goodNight' => <<<'CODE'
// Scene "Good night": lights off and a lower target temperature.
// The thermostat stays in heat mode, so it will keep the night setback.
callMethod('StarterLamp.turnOff');
sg('StarterThermostat.mode', 'heat');
sg('StarterThermostat.targetTemp', (float)$this->getProperty('nightTarget'));

$this->setProperty('presence', 1);
$this->setProperty('activeScene', 'night');
$this->setProperty('lastActivated', date('Y-m-d H:i:s'));
callMethod('StarterRoom.refresh');
CODE
                ,
                'away' => <<<'CODE'
// Scene "Away": nobody is home, the lamp is off and the thermostat is off.
callMethod('StarterLamp.turnOff');
sg('StarterThermostat.mode', 'off');

$this->setProperty('presence', 0);
$this->setProperty('activeScene', 'away');
$this->setProperty('lastActivated', date('Y-m-d H:i:s'));
callMethod('StarterRoom.refresh');
CODE
                ,
                'home' => <<<'CODE'
// Scene "Home": somebody is back, a soft light and the day target.
callMethod('StarterLamp.setBrightness', array('level' => (int)$this->getProperty('homeLevel')));
sg('StarterThermostat.mode', 'heat');
sg('StarterThermostat.targetTemp', (float)$this->getProperty('dayTarget'));

$this->setProperty('presence', 1);
$this->setProperty('activeScene', 'home');
$this->setProperty('lastActivated', date('Y-m-d H:i:s'));
callMethod('StarterRoom.refresh');
CODE
            )
        )
    ),
    'objects' => array(
        array(
            'title' => 'StarterScenes',
            'class' => 'SK_Scene',
            'description' => '{{SCENE_OBJ_DESC}}',
            'properties' => array(
                'activeScene' => '',
                'presence' => 1,
                'morningLevel' => 70,
                'homeLevel' => 40,
                'dayTarget' => 22,
                'nightTarget' => 18
            )
        )
    ),
    'scenes' => array(
        array(
            'title' => '{{SCENE_MORNING}}',
            'priority' => 40,
            'elements' => array(
                array(
                    'title' => '{{SCENE_MORNING}}',
                    'type' => 'button',
                    'top' => 100,
                    'left' => 100,
                    'width' => 260,
                    'height' => 60,
                    'action_object' => 'StarterScenes',
                    'action_method' => 'goodMorning'
                )
            )
        ),
        array(
            'title' => '{{SCENE_NIGHT}}',
            'priority' => 30,
            'elements' => array(
                array(
                    'title' => '{{SCENE_NIGHT}}',
                    'type' => 'button',
                    'top' => 100,
                    'left' => 100,
                    'width' => 260,
                    'height' => 60,
                    'action_object' => 'StarterScenes',
                    'action_method' => 'goodNight'
                )
            )
        ),
        array(
            'title' => '{{SCENE_AWAY}}',
            'priority' => 20,
            'elements' => array(
                array(
                    'title' => '{{SCENE_AWAY}}',
                    'type' => 'button',
                    'top' => 100,
                    'left' => 100,
                    'width' => 260,
                    'height' => 60,
                    'action_object' => 'StarterScenes',
                    'action_method' => 'away'
                )
            )
        ),
        array(
            'title' => '{{SCENE_HOME}}',
            'priority' => 10,
            'elements' => array(
                array(
                    'title' => '{{SCENE_HOME}}',
                    'type' => 'button',
                    'top' => 100,
                    'left' => 100,
                    'width' => 260,
                    'height' => 60,
                    'action_object' => 'StarterScenes',
                    'action_method' => 'home'
                )
            )
        )
    )
);
