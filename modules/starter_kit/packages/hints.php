<?php
/**
 * Starter Kit package: hints.
 *
 * Adds a beginner guide as a regular text file (Control panel -> Text files)
 * and a script that loads it into the StarterHints object, so the panel can
 * show the same content. The per-package hints are displayed on the module
 * page from each package "hints" section.
 */

return array(
    'id' => 'hints',
    'version' => 1,
    'requires' => array('basic_entities'),
    'title_key' => 'STARTER_KIT_PKG_HINTS_TITLE',
    'description_key' => 'STARTER_KIT_PKG_HINTS_DESC',
    'hints' => array(
        'shows' => 'STARTER_KIT_HINT_HINTS_SHOWS',
        'adapt' => 'STARTER_KIT_HINT_HINTS_ADAPT'
    ),
    'texts' => array(
        'HINTS_DESC' => 'STARTER_KIT_TEXT_HINTS_DESC',
        'HINTS_OBJ_DESC' => 'STARTER_KIT_TEXT_HINTS_OBJ_DESC',
        'HINT_FILE_MISSING' => 'STARTER_KIT_TEXT_HINT_FILE_MISSING',
        'GUIDE_TITLE' => 'STARTER_KIT_TEXT_GUIDE_TITLE',
        'GUIDE_INTRO' => 'STARTER_KIT_TEXT_GUIDE_INTRO',
        'GUIDE_STEP1' => 'STARTER_KIT_TEXT_GUIDE_STEP1',
        'GUIDE_STEP2' => 'STARTER_KIT_TEXT_GUIDE_STEP2',
        'GUIDE_STEP3' => 'STARTER_KIT_TEXT_GUIDE_STEP3',
        'GUIDE_STEP4' => 'STARTER_KIT_TEXT_GUIDE_STEP4',
        'GUIDE_PACKAGES' => 'STARTER_KIT_TEXT_GUIDE_PACKAGES',
        'GUIDE_PKG_BASIC' => 'STARTER_KIT_TEXT_GUIDE_PKG_BASIC',
        'GUIDE_PKG_MODES' => 'STARTER_KIT_TEXT_GUIDE_PKG_MODES',
        'GUIDE_PKG_SCENES' => 'STARTER_KIT_TEXT_GUIDE_PKG_SCENES',
        'GUIDE_PKG_SCRIPTS' => 'STARTER_KIT_TEXT_GUIDE_PKG_SCRIPTS',
        'GUIDE_PKG_PANEL' => 'STARTER_KIT_TEXT_GUIDE_PKG_PANEL',
        'GUIDE_PKG_HINTS' => 'STARTER_KIT_TEXT_GUIDE_PKG_HINTS',
        'GUIDE_PKG_CLOCK' => 'STARTER_KIT_TEXT_GUIDE_PKG_CLOCK',
        'GUIDE_PKG_SUN' => 'STARTER_KIT_TEXT_GUIDE_PKG_SUN',
        'GUIDE_SAFE' => 'STARTER_KIT_TEXT_GUIDE_SAFE',
        'GUIDE_LINK' => 'STARTER_KIT_TEXT_GUIDE_LINK'
    ),
    'classes' => array(
        array(
            'title' => 'SK_Hints',
            'description' => '{{HINTS_DESC}}',
            'properties' => array(
                'text' => array(),
                'updated' => array()
            ),
            'methods' => array()
        )
    ),
    'objects' => array(
        array(
            'title' => 'StarterHints',
            'class' => 'SK_Hints',
            'description' => '{{HINTS_OBJ_DESC}}'
        )
    ),
    'text_files' => array(
        array(
            'name' => 'starter_kit_hints',
            'content' => <<<'TEXT'
{{GUIDE_TITLE}}

{{GUIDE_INTRO}}

{{GUIDE_STEP1}}
{{GUIDE_STEP2}}
{{GUIDE_STEP3}}
{{GUIDE_STEP4}}

{{GUIDE_PACKAGES}}
- Basic entities: {{GUIDE_PKG_BASIC}}
- Modes: {{GUIDE_PKG_MODES}}
- Scenes: {{GUIDE_PKG_SCENES}}
- Scripts: {{GUIDE_PKG_SCRIPTS}}
- Panel: {{GUIDE_PKG_PANEL}}
- Hints: {{GUIDE_PKG_HINTS}}
- Clock and chime: {{GUIDE_PKG_CLOCK}}
- Time and sun: {{GUIDE_PKG_SUN}}

{{GUIDE_SAFE}}

{{GUIDE_LINK}}
TEXT
        )
    ),
    'scripts' => array(
        array(
            'title' => 'SK_ShowHints',
            'description' => '{{HINTS_OBJ_DESC}}',
            'code' => <<<'CODE'
// Load the beginner guide from cms/texts/starter_kit_hints.txt and put it
// into StarterHints.text, so the panel can display it.
$file = ROOT . 'cms/texts/starter_kit_hints.txt';
if (!file_exists($file)) {
    sg('StarterHints.text', '{{HINT_FILE_MISSING}}');
    return;
}
$text = LoadFile($file);
sg('StarterHints.text', $text);
sg('StarterHints.updated', date('Y-m-d H:i:s'));
DebMes('StarterKit hints shown', 'starter_kit');
CODE
        )
    )
);
