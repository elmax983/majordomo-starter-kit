<?php
/**
 * Starter Kit package: panel.
 *
 * Adds a frontend page (layouts table, TYPE=html) with live cards for the demo
 * entities. The values use the built-in %Object.Property|seconds% placeholders:
 * MajorDoMo replaces them and refreshes them over websockets, or by AJAX when
 * websockets are disabled.
 *
 * The page appears as a tab on the main frontend and can be edited later in
 * Control panel -> Pages. Requires basic_entities, scenes and scripts.
 */

return array(
    'id' => 'panel',
    'version' => 1,
    'requires' => array('basic_entities', 'scenes', 'scripts'),
    'title_key' => 'STARTER_KIT_PKG_PANEL_TITLE',
    'description_key' => 'STARTER_KIT_PKG_PANEL_DESC',
    'texts' => array(
        'PANEL_TITLE' => 'STARTER_KIT_TEXT_PANEL_TITLE',
        'PANEL_INTRO' => 'STARTER_KIT_TEXT_PANEL_INTRO',
        'PANEL_NOTE' => 'STARTER_KIT_TEXT_PANEL_NOTE',
        'CARD_LAMP' => 'STARTER_KIT_TEXT_CARD_LAMP',
        'CARD_SENSOR' => 'STARTER_KIT_TEXT_CARD_SENSOR',
        'CARD_THERMOSTAT' => 'STARTER_KIT_TEXT_CARD_THERMOSTAT',
        'CARD_ROOM' => 'STARTER_KIT_TEXT_CARD_ROOM',
        'CARD_SCENES' => 'STARTER_KIT_TEXT_CARD_SCENES',
        'CARD_SCRIPTS' => 'STARTER_KIT_TEXT_CARD_SCRIPTS',
        'LABEL_BRIGHTNESS' => 'STARTER_KIT_TEXT_LABEL_BRIGHTNESS',
        'LABEL_CURRENT' => 'STARTER_KIT_TEXT_LABEL_CURRENT',
        'LABEL_TARGET' => 'STARTER_KIT_TEXT_LABEL_TARGET',
        'LABEL_DEVICES' => 'STARTER_KIT_TEXT_LABEL_DEVICES',
        'LABEL_UPDATED' => 'STARTER_KIT_TEXT_LABEL_UPDATED',
        'LABEL_ACTIVE_SCENE' => 'STARTER_KIT_TEXT_LABEL_ACTIVE_SCENE',
        'LABEL_GREETING' => 'STARTER_KIT_TEXT_LABEL_GREETING',
        'LABEL_CONNECTION' => 'STARTER_KIT_TEXT_LABEL_CONNECTION',
        'LABEL_LAST_ACTION' => 'STARTER_KIT_TEXT_LABEL_LAST_ACTION',
        'OPEN_SCENES' => 'STARTER_KIT_TEXT_OPEN_SCENES'
    ),
    'layouts' => array(
        array(
            'title' => '{{PANEL_TITLE}}',
            'type' => 'html',
            'priority' => 50,
            'code' => <<<'HTML'
<div class="row" style="padding:0 15px 20px 15px;">
    <div class="col-md-12">
        <h2 style="margin-top:0;">{{PANEL_TITLE}}</h2>
        <p class="text-muted">{{PANEL_INTRO}}</p>
    </div>

    <div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-heading"><b>{{CARD_LAMP}}</b></div>
            <div class="panel-body">
                <div style="font-size:26px;line-height:32px;">%StarterLamp.status|10%</div>
                <div class="text-muted">{{LABEL_BRIGHTNESS}}: %StarterLamp.brightness|10%</div>
                <div class="small text-muted">{{LABEL_UPDATED}}: %StarterLamp.lastChanged|30%</div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-heading"><b>{{CARD_SENSOR}}</b></div>
            <div class="panel-body">
                <div style="font-size:26px;line-height:32px;">%StarterSensor.status|10%</div>
                <div class="small text-muted">{{LABEL_UPDATED}}: %StarterSensor.lastChanged|30%</div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-heading"><b>{{CARD_THERMOSTAT}}</b></div>
            <div class="panel-body">
                <div style="font-size:26px;line-height:32px;">%StarterThermostat.status|10%</div>
                <div>{{LABEL_CURRENT}}: %StarterThermostat.currentTemp|10% &deg;C</div>
                <div>{{LABEL_TARGET}}: %StarterThermostat.targetTemp|30% &deg;C</div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading"><b>{{CARD_ROOM}}</b></div>
            <div class="panel-body">
                <div style="font-size:15px;line-height:22px;">%StarterRoom.summary|10%</div>
                <div class="small text-muted">{{LABEL_DEVICES}}: %StarterRoom.deviceCount|30% &middot;
                    {{LABEL_UPDATED}}: %StarterRoom.updated|30%</div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading"><b>{{CARD_SCENES}}</b></div>
            <div class="panel-body">
                <div>{{LABEL_ACTIVE_SCENE}}: <b>%StarterScenes.activeScene|10%</b></div>
                <div class="small text-muted">{{LABEL_UPDATED}}: %StarterScenes.lastActivated|30%</div>
                <p style="margin-top:12px;">
                    <a class="btn btn-default btn-sm" href="<#ROOTHTML#>popup/scenes.html" target="_blank">{{OPEN_SCENES}}</a>
                </p>
            </div>
        </div>
    </div>

    <div class="col-md-12">
        <div class="panel panel-default">
            <div class="panel-heading"><b>{{CARD_SCRIPTS}}</b></div>
            <div class="panel-body">
                <div>{{LABEL_GREETING}}: <b>%StarterScripts.greeting|30%</b></div>
                <div>{{LABEL_CONNECTION}}: <b>%StarterScripts.connectionStatus|30%</b>
                    (%StarterScripts.connectionDetails|30%)</div>
                <div class="small text-muted">{{LABEL_LAST_ACTION}}: %StarterScripts.lastAction|10%</div>
            </div>
        </div>
    </div>

    <div class="col-md-12">
        <p class="small text-muted">{{PANEL_NOTE}}</p>
    </div>
</div>
HTML
        )
    )
);
