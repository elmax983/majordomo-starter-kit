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
    'version' => 2,
    'requires' => array('basic_entities', 'modes'),
    'title_key' => 'STARTER_KIT_PKG_SCENES_TITLE',
    'description_key' => 'STARTER_KIT_PKG_SCENES_DESC',
    'hints' => array(
        'shows' => 'STARTER_KIT_HINT_SCENES_SHOWS',
        'adapt' => 'STARTER_KIT_HINT_SCENES_ADAPT'
    ),
    'texts' => array(
        'SCENE_DESC' => 'STARTER_KIT_TEXT_SCENE_DESC',
        'SCENE_OBJ_DESC' => 'STARTER_KIT_TEXT_SCENE_OBJ_DESC',
        'SCENE_MORNING' => 'STARTER_KIT_TEXT_SCENE_MORNING',
        'SCENE_NIGHT' => 'STARTER_KIT_TEXT_SCENE_NIGHT',
        'SCENE_AWAY' => 'STARTER_KIT_TEXT_SCENE_AWAY',
        'SCENE_HOME' => 'STARTER_KIT_TEXT_SCENE_HOME',
        'SCENE_MOVIE' => 'STARTER_KIT_TEXT_SCENE_MOVIE',
        'SCENE_GUEST' => 'STARTER_KIT_TEXT_SCENE_GUEST'
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
                'nightTarget' => array(),
                'movieLevel' => array(),
                'guestLevel' => array(),
                'guestTarget' => array()
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
                ,
                'movieNight' => <<<'CODE'
// Scene "Movie night": dim light and a comfortable temperature.
callMethod('StarterModes.setMode', array('mode' => 'vacation', 'active' => 0));
callMethod('StarterModes.setMode', array('mode' => 'guest', 'active' => 0));
callMethod('StarterLamp.setBrightness', array('level' => (int)$this->getProperty('movieLevel')));
sg('StarterThermostat.mode', 'heat');
sg('StarterThermostat.targetTemp', (float)$this->getProperty('dayTarget'));

$this->setProperty('presence', 1);
$this->setProperty('activeScene', 'movie');
$this->setProperty('lastActivated', date('Y-m-d H:i:s'));
callMethod('StarterRoom.refresh');
CODE
                ,
                'guest' => <<<'CODE'
// Scene "Guest": somebody is visiting. Guest mode handles the devices,
// the scene only sets it and records the active scene.
callMethod('StarterModes.setMode', array('mode' => 'guest', 'active' => 1));
callMethod('StarterModes.setMode', array('mode' => 'vacation', 'active' => 0));
callMethod('StarterLamp.setBrightness', array('level' => (int)$this->getProperty('guestLevel')));
sg('StarterThermostat.targetTemp', (float)$this->getProperty('guestTarget'));

$this->setProperty('presence', 1);
$this->setProperty('activeScene', 'guest');
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
                'nightTarget' => 18,
                'movieLevel' => 15,
                'guestLevel' => 40,
                'guestTarget' => 21
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
            'title' => '{{SCENE_MOVIE}}',
            'priority' => 25,
            'elements' => array(
                array(
                    'title' => '{{SCENE_MOVIE}}',
                    'type' => 'button',
                    'top' => 100,
                    'left' => 100,
                    'width' => 260,
                    'height' => 60,
                    'action_object' => 'StarterScenes',
                    'action_method' => 'movieNight'
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
        ),
        array(
            'title' => '{{SCENE_GUEST}}',
            'priority' => 5,
            'elements' => array(
                array(
                    'title' => '{{SCENE_GUEST}}',
                    'type' => 'button',
                    'top' => 100,
                    'left' => 100,
                    'width' => 260,
                    'height' => 60,
                    'action_object' => 'StarterScenes',
                    'action_method' => 'guest'
                )
            )
        )
    )
);
