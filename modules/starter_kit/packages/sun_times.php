<?php
/**
 * Starter Kit package: sun times and day parts.
 *
 * Uses PHP's built-in date_sun_info() only: no external APIs, no network.
 * Coordinates are stored in the StarterSun object, so a user can change them
 * without editing the script.
 */

return array(
    'id' => 'sun_times',
    'version' => 1,
    'title_key' => 'STARTER_KIT_PKG_SUN_TITLE',
    'description_key' => 'STARTER_KIT_PKG_SUN_DESC',
    'hints' => array(
        'shows' => 'STARTER_KIT_HINT_SUN_SHOWS',
        'adapt' => 'STARTER_KIT_HINT_SUN_ADAPT'
    ),
    'classes' => array(
        array(
            'title' => 'SK_Sun',
            'parent' => 'Timer',
            'description' => 'Starter Kit: offline sun times and day parts',
            'properties' => array(
                'latitude' => array(),
                'longitude' => array(),
                'sunrise' => array(),
                'sunset' => array(),
                'civilDawn' => array(),
                'civilDusk' => array(),
                'nauticalDawn' => array(),
                'nauticalDusk' => array(),
                'astroDawn' => array(),
                'astroDusk' => array(),
                'dayPart' => array(),
                'lastCalcDate' => array(),
                'calcStatus' => array()
            ),
            'methods' => array()
        )
    ),
    'objects' => array(
        array(
            'title' => 'StarterSun',
            'class' => 'SK_Sun',
            'description' => 'Starter Kit: sun times for the configured coordinates',
            'properties' => array(
                // Example coordinates (Moscow). Change latitude/longitude
                // to the values for your own city.
                'latitude' => 55.7558,
                'longitude' => 37.6173,
                'dayPart' => '',
                'calcStatus' => ''
            ),
            'methods' => array(
                'onNewMinute' => <<<'CODE'
// Recalculate sun times once a day and update the current day part.
if (gg('StarterSun.lastCalcDate') != date('Y-m-d')) {
    runScript('SK_SunTimes');
}

$now = date('H:i');
$sunrise = gg('StarterSun.sunrise');
$sunset = gg('StarterSun.sunset');
$civilDawn = gg('StarterSun.civilDawn');
$civilDusk = gg('StarterSun.civilDusk');

$dayPart = 'night';
if ($sunrise != '' && $sunrise != '--:--' && $sunset != '' && $sunset != '--:--') {
    if ($now >= $sunrise && $now < $sunset) {
        $dayPart = 'day';
    } elseif ($civilDawn != '' && $civilDawn != '--:--' && $now >= $civilDawn && $now < $sunrise) {
        $dayPart = 'dawn';
    } elseif ($civilDusk != '' && $civilDusk != '--:--' && $now >= $sunset && $now < $civilDusk) {
        $dayPart = 'dusk';
    }
}
sg('StarterSun.dayPart', $dayPart);
CODE
                ,
                'onNewDay' => <<<'CODE'
// Force a recalculation right after midnight.
runScript('SK_SunTimes');
CODE
            )
        )
    ),
    'scripts' => array(
        array(
            'title' => 'SK_SunTimes',
            'description' => 'Starter Kit: calculates sunrise, sunset and twilight times for StarterSun',
            'code' => <<<'CODE'
$latitude_raw = trim(gg('StarterSun.latitude'));
$longitude_raw = trim(gg('StarterSun.longitude'));

if ($latitude_raw === '' || $longitude_raw === '') {
    sg('StarterSun.calcStatus', 'coordinates_not_set');
    return;
}

$latitude = (float)$latitude_raw;
$longitude = (float)$longitude_raw;
if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    sg('StarterSun.calcStatus', 'coordinates_invalid');
    return;
}

// date_sun_info() uses the UTC date of the given timestamp, so take noon UTC
// on the current local date to avoid a date shift in eastern time zones.
$timestamp = gmmktime(12, 0, 0, (int)date('n'), (int)date('j'), (int)date('Y'));
$info = date_sun_info($timestamp, $latitude, $longitude);

$map = array(
    'sunrise' => 'sunrise',
    'sunset' => 'sunset',
    'civil_twilight_begin' => 'civilDawn',
    'civil_twilight_end' => 'civilDusk',
    'nautical_twilight_begin' => 'nauticalDawn',
    'nautical_twilight_end' => 'nauticalDusk',
    'astronomical_twilight_begin' => 'astroDawn',
    'astronomical_twilight_end' => 'astroDusk'
);

$found = 0;
foreach ($map as $source => $target) {
    if (isset($info[$source]) && is_numeric($info[$source]) && $info[$source] > 0) {
        sg('StarterSun.' . $target, date('H:i', (int)$info[$source]));
        $found++;
    } else {
        sg('StarterSun.' . $target, '--:--');
    }
}

sg('StarterSun.lastCalcDate', date('Y-m-d'));
if ($found == 0) {
    sg('StarterSun.calcStatus', 'polar_day_or_night');
} else {
    sg('StarterSun.calcStatus', 'ok');
}
CODE
        )
    )
);
