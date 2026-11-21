<?php
/**
 * Starter Kit package: basic entities.
 *
 * Demonstrates the everyday building blocks of MajorDoMo:
 * - a class hierarchy: SK_Device -> SK_Lamp / SK_Sensor / SK_Button / SK_Thermostat;
 * - class properties, including property onchange hooks;
 * - class methods, including methods with parameters;
 * - an object that collects the status of other objects.
 *
 * Everything is virtual: no hardware, no MQTT, no external services.
 * Classes are listed parent-first: the installer creates them in this order.
 */

return array(
    'id' => 'basic_entities',
    'version' => 1,
    'title_key' => 'STARTER_KIT_PKG_BASIC_TITLE',
    'description_key' => 'STARTER_KIT_PKG_BASIC_DESC',
    'hints' => array(
        'shows' => 'STARTER_KIT_HINT_BASIC_SHOWS',
        'adapt' => 'STARTER_KIT_HINT_BASIC_ADAPT'
    ),
    'texts' => array(
        'ON' => 'STARTER_KIT_TEXT_ON',
        'OFF' => 'STARTER_KIT_TEXT_OFF',
        'DEVICE_DESC' => 'STARTER_KIT_TEXT_DEVICE_DESC',
        'LAMP_DESC' => 'STARTER_KIT_TEXT_LAMP_DESC',
        'SENSOR_DESC' => 'STARTER_KIT_TEXT_SENSOR_DESC',
        'BUTTON_DESC' => 'STARTER_KIT_TEXT_BUTTON_DESC',
        'THERMOSTAT_DESC' => 'STARTER_KIT_TEXT_THERMOSTAT_DESC',
        'ROOM_DESC' => 'STARTER_KIT_TEXT_ROOM_DESC',
        'LAMP_OBJ_DESC' => 'STARTER_KIT_TEXT_LAMP_OBJ_DESC',
        'SENSOR_OBJ_DESC' => 'STARTER_KIT_TEXT_SENSOR_OBJ_DESC',
        'BUTTON_OBJ_DESC' => 'STARTER_KIT_TEXT_BUTTON_OBJ_DESC',
        'THERMOSTAT_OBJ_DESC' => 'STARTER_KIT_TEXT_THERMOSTAT_OBJ_DESC',
        'ROOM_OBJ_DESC' => 'STARTER_KIT_TEXT_ROOM_OBJ_DESC',
        'ROOM_NAME' => 'STARTER_KIT_TEXT_ROOM_NAME',
        'PRESS_COUNT' => 'STARTER_KIT_TEXT_PRESS_COUNT',
        'INVALID_VALUE' => 'STARTER_KIT_TEXT_INVALID_VALUE',
        'NO_STATUS' => 'STARTER_KIT_TEXT_NO_STATUS',
        'HEATING' => 'STARTER_KIT_TEXT_HEATING',
        'COOLING' => 'STARTER_KIT_TEXT_COOLING',
        'IDLE' => 'STARTER_KIT_TEXT_IDLE',
        'DEMO_DESC' => 'STARTER_KIT_TEXT_DEMO_DESC'
    ),
    'classes' => array(
        array(
            'title' => 'SK_Device',
            'description' => '{{DEVICE_DESC}}',
            'template' => '<b>%.object_title%</b>: %.status%',
            'properties' => array(
                'state' => array('onchange' => 'onStateChanged'),
                'status' => array(),
                'lastChanged' => array()
            ),
            'methods' => array(
                'turnOn' => <<<'CODE'
// Switch the device on. The "state" property has an onchange hook,
// so "status" and "lastChanged" are updated automatically.
$this->setProperty('state', 1);
CODE
                ,
                'turnOff' => <<<'CODE'
// Switch the device off.
$this->setProperty('state', 0);
CODE
                ,
                'toggle' => <<<'CODE'
// Flip the current state. Handy for buttons and simple automations.
$state = (int)$this->getProperty('state');
$this->setProperty('state', $state ? 0 : 1);
CODE
                ,
                'onStateChanged' => <<<'CODE'
// Property onchange handler. MajorDoMo passes $params with
// PROPERTY, NEW_VALUE, OLD_VALUE and SOURCE.
// On a real change only, remember the time.
$oldValue = isset($params['OLD_VALUE']) ? (string)$params['OLD_VALUE'] : '';
$newValue = isset($params['NEW_VALUE']) ? (string)$params['NEW_VALUE'] : (string)$this->getProperty('state');
if ($oldValue !== $newValue) {
    $this->setProperty('lastChanged', date('Y-m-d H:i:s'));
}
$this->setProperty('status', (int)$newValue ? '{{ON}}' : '{{OFF}}');
CODE
            )
        ),
        array(
            'title' => 'SK_Lamp',
            'parent' => 'SK_Device',
            'description' => '{{LAMP_DESC}}',
            'template' => '<b>%.object_title%</b>: %.status% (%.brightness% / 100)',
            'properties' => array(
                'brightness' => array('onchange' => 'onBrightnessChanged')
            ),
            'methods' => array(
                'setBrightness' => <<<'CODE'
// Usage: callMethod('StarterLamp.setBrightness', array('level' => 60));
// The level is clamped to 0..100. Zero switches the lamp off.
$level = 0;
if (is_array($params) && isset($params['level'])) {
    $level = $params['level'];
} elseif (is_array($params) && isset($params['value'])) {
    $level = $params['value'];
}
$level = max(0, min(100, (float)$level));
$this->setProperty('brightness', $level);
$this->setProperty('state', $level > 0 ? 1 : 0);
CODE
                ,
                'onStateChanged' => <<<'CODE'
// Override of the base handler: a lamp shows both state and brightness.
$oldValue = isset($params['OLD_VALUE']) ? (string)$params['OLD_VALUE'] : '';
$newValue = isset($params['NEW_VALUE']) ? (string)$params['NEW_VALUE'] : (string)$this->getProperty('state');
if ($oldValue !== $newValue) {
    $this->setProperty('lastChanged', date('Y-m-d H:i:s'));
}
$this->callMethod('refreshStatus');
CODE
                ,
                'onBrightnessChanged' => <<<'CODE'
// Brightness changed: refresh the text and the change time.
$this->setProperty('lastChanged', date('Y-m-d H:i:s'));
$this->callMethod('refreshStatus');
CODE
                ,
                'refreshStatus' => <<<'CODE'
// Build a human readable status from the current values.
// It is called by both onchange handlers, so the order of changes
// does not matter.
$state = (int)$this->getProperty('state');
if (!$state) {
    $this->setProperty('status', '{{OFF}}');
    return;
}
$level = (int)round((float)$this->getProperty('brightness'));
$this->setProperty('status', '{{ON}} ' . $level . '%');
CODE
            )
        ),
        array(
            'title' => 'SK_Sensor',
            'parent' => 'SK_Device',
            'description' => '{{SENSOR_DESC}}',
            'template' => '<b>%.object_title%</b>: %.status%',
            'properties' => array(
                'value' => array('onchange' => 'onValueChanged'),
                'unit' => array('onchange' => 'onValueChanged')
            ),
            'methods' => array(
                'setValue' => <<<'CODE'
// Usage: callMethod('StarterSensor.setValue', array('value' => 21.5));
// Non-numeric input is rejected and reported in "status".
$value = null;
if (is_array($params) && isset($params['value'])) {
    $value = $params['value'];
} elseif (is_array($params) && isset($params['NEW_VALUE'])) {
    $value = $params['NEW_VALUE'];
}
if ($value === null || $value === '') {
    return;
}
if (!is_numeric($value)) {
    $this->setProperty('status', '{{INVALID_VALUE}}');
    return;
}
$this->setProperty('value', (float)$value);
CODE
                ,
                'onValueChanged' => <<<'CODE'
// The onchange hook builds "21.5 °C" from the value and the unit.
$value = trim((string)$this->getProperty('value'));
$unit = trim((string)$this->getProperty('unit'));
$this->setProperty('status', trim($value . ' ' . $unit));
$this->setProperty('lastChanged', date('Y-m-d H:i:s'));
CODE
            )
        ),
        array(
            'title' => 'SK_Button',
            'parent' => 'SK_Device',
            'description' => '{{BUTTON_DESC}}',
            'template' => '<b>%.object_title%</b>: %.status%',
            'properties' => array(
                'pressCount' => array(),
                'lastPressed' => array()
            ),
            'methods' => array(
                'press' => <<<'CODE'
// A plain method with a counter: no onchange needed here.
$count = (int)$this->getProperty('pressCount') + 1;
$this->setProperty('pressCount', $count);
$this->setProperty('lastPressed', date('Y-m-d H:i:s'));
$this->setProperty('status', '{{PRESS_COUNT}}: ' . $count);
CODE
                ,
                'reset' => <<<'CODE'
// Reset the press counter.
$this->setProperty('pressCount', 0);
$this->setProperty('status', '{{PRESS_COUNT}}: 0');
CODE
            )
        ),
        array(
            'title' => 'SK_Thermostat',
            'parent' => 'SK_Device',
            'description' => '{{THERMOSTAT_DESC}}',
            'template' => '<b>%.object_title%</b>: %.status% (%.currentTemp% &rarr; %.targetTemp%)',
            'properties' => array(
                'currentTemp' => array('onchange' => 'onValueChanged'),
                'targetTemp' => array('onchange' => 'onSettingChanged'),
                'mode' => array('onchange' => 'onSettingChanged'),
                'hysteresis' => array()
            ),
            'methods' => array(
                'tick' => <<<'CODE'
// Simple hysteresis control loop.
// Run it manually: callMethod('StarterThermostat.tick');
// or from a scene, script or your own onNewMinute handler.
$mode = strtolower(trim((string)$this->getProperty('mode')));
$current = (float)$this->getProperty('currentTemp');
$target = (float)$this->getProperty('targetTemp');
$hysteresis = (float)$this->getProperty('hysteresis');
if ($hysteresis <= 0) {
    $hysteresis = 0.5;
}

$state = (int)$this->getProperty('state');
if ($mode != 'heat' && $mode != 'cool') {
    $state = 0; // off or unknown mode
} elseif ($mode == 'heat') {
    if ($current < $target - $hysteresis) {
        $state = 1; // too cold: heating on
    } elseif ($current > $target + $hysteresis) {
        $state = 0; // target reached: heating off
    }
} else {
    if ($current > $target + $hysteresis) {
        $state = 1; // too hot: cooling on
    } elseif ($current < $target - $hysteresis) {
        $state = 0; // target reached: cooling off
    }
}
$this->setProperty('state', $state);
CODE
                ,
                'onValueChanged' => <<<'CODE'
// The measured temperature changed: re-evaluate the control loop.
$this->callMethod('tick');
CODE
                ,
                'onSettingChanged' => <<<'CODE'
// Mode, target or hysteresis changed: re-evaluate the control loop.
$this->callMethod('tick');
CODE
                ,
                'onStateChanged' => <<<'CODE'
// Override of the base handler: show heating/cooling/idle in words.
$oldValue = isset($params['OLD_VALUE']) ? (string)$params['OLD_VALUE'] : '';
$newValue = isset($params['NEW_VALUE']) ? (string)$params['NEW_VALUE'] : (string)$this->getProperty('state');
if ($oldValue !== $newValue) {
    $this->setProperty('lastChanged', date('Y-m-d H:i:s'));
}
$this->callMethod('refreshStatus');
CODE
                ,
                'refreshStatus' => <<<'CODE'
// Build the text status from mode and state.
$mode = strtolower(trim((string)$this->getProperty('mode')));
$state = (int)$this->getProperty('state');
if ($mode != 'heat' && $mode != 'cool') {
    $this->setProperty('status', '{{OFF}}');
} elseif (!$state) {
    $this->setProperty('status', '{{IDLE}}');
} elseif ($mode == 'heat') {
    $this->setProperty('status', '{{HEATING}}');
} else {
    $this->setProperty('status', '{{COOLING}}');
}
CODE
            )
        ),
        array(
            'title' => 'SK_Room',
            'description' => '{{ROOM_DESC}}',
            'template' => '<b>%.object_title%</b>: %.summary%',
            'properties' => array(
                'roomName' => array(),
                'deviceList' => array(),
                'deviceCount' => array(),
                'summary' => array(),
                'updated' => array()
            ),
            'methods' => array(
                'refresh' => <<<'CODE'
// Collect the status of every object listed in "deviceList"
// (comma separated object titles) and build a short summary.
$list = trim((string)$this->getProperty('deviceList'));
$parts = array();
$count = 0;
if ($list !== '') {
    foreach (explode(',', $list) as $title) {
        $title = trim($title);
        if ($title === '') {
            continue;
        }
        $status = getGlobal($title . '.status');
        if ($status === false || $status === null || $status === '') {
            $status = '{{NO_STATUS}}';
        }
        $parts[] = $title . ': ' . $status;
        $count++;
    }
}
$this->setProperty('deviceCount', $count);
$this->setProperty('summary', implode('; ', $parts));
$this->setProperty('updated', date('Y-m-d H:i:s'));
CODE
            )
        )
    ),
    'objects' => array(
        array(
            'title' => 'StarterLamp',
            'class' => 'SK_Lamp',
            'description' => '{{LAMP_OBJ_DESC}}',
            'properties' => array(
                'state' => 0,
                'brightness' => 0
            )
        ),
        array(
            'title' => 'StarterSensor',
            'class' => 'SK_Sensor',
            'description' => '{{SENSOR_OBJ_DESC}}',
            'properties' => array(
                'state' => 0,
                'value' => 0,
                'unit' => '°C'
            )
        ),
        array(
            'title' => 'StarterButton',
            'class' => 'SK_Button',
            'description' => '{{BUTTON_OBJ_DESC}}',
            'properties' => array(
                'state' => 0,
                'pressCount' => 0
            )
        ),
        array(
            'title' => 'StarterThermostat',
            'class' => 'SK_Thermostat',
            'description' => '{{THERMOSTAT_OBJ_DESC}}',
            'properties' => array(
                'state' => 0,
                'currentTemp' => 20,
                'targetTemp' => 22,
                'mode' => 'heat',
                'hysteresis' => 0.5
            )
        ),
        array(
            'title' => 'StarterRoom',
            'class' => 'SK_Room',
            'description' => '{{ROOM_OBJ_DESC}}',
            'properties' => array(
                'roomName' => '{{ROOM_NAME}}',
                'deviceList' => 'StarterLamp,StarterSensor,StarterButton,StarterThermostat'
            )
        )
    ),
    'scripts' => array(
        array(
            'title' => 'SK_EntitiesDemo',
            'description' => '{{DEMO_DESC}}',
            'code' => <<<'CODE'
// Starter Kit: a short tour of the basic entities.
// Open the objects StarterLamp, StarterSensor, StarterButton,
// StarterThermostat and StarterRoom to see the properties change.
// The final summary is also written to cms/debmes/<date>/starter_kit.log.

// 1. Device on/off and toggle: methods of the SK_Device class.
callMethod('StarterLamp.turnOn');
callMethod('StarterLamp.toggle');
callMethod('StarterLamp.turnOn');

// 2. Brightness: SK_Lamp::onBrightnessChanged updates status automatically.
callMethod('StarterLamp.setBrightness', array('level' => 60));

// 3. Sensor: setValue validates the input, onValueChanged builds the text.
callMethod('StarterSensor.setValue', array('value' => 21.5));

// 4. Button: a method with a counter.
callMethod('StarterButton.press');
callMethod('StarterButton.press');

// 5. Thermostat: the room is colder than the target, so heating starts.
sg('StarterThermostat.currentTemp', 19.5);
sg('StarterThermostat.targetTemp', 22);
callMethod('StarterThermostat.tick');

// 6. Room: collect the status of all devices from its deviceList property.
callMethod('StarterRoom.refresh');

DebMes('StarterRoom: ' . gg('StarterRoom.summary'), 'starter_kit');
CODE
        )
    )
);
