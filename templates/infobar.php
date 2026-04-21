<?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * NAWS Infobar Template
 *
 * Shortcode: [naws_infobar]
 * Displays: Feels-like, Dew Point, Heat Index (>=25°C), Sunrise, Sunset, Moon Phase, Next Full Moon
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Get latest readings + module type map ────────────────────────────────────
$latest     = NAWS_Database::get_latest_readings();
$modules    = NAWS_Database::get_modules();
$type_map   = [];
foreach ( $modules as $m ) {
    $type_map[ $m['module_id'] ] = $m['module_type'];
}

$readings = [];
foreach ( $latest as $row ) {
    $mtype = $type_map[ $row['module_id'] ] ?? '';
    $key   = $mtype . '||' . $row['parameter'];
    $readings[ $key ] = floatval( $row['value'] );
}

// Outdoor: NAModule1
$temp_out = $readings['NAModule1||Temperature'] ?? null;
$hum_out  = $readings['NAModule1||Humidity']    ?? null;
// Wind: NAModule2
$wind     = $readings['NAModule2||WindStrength'] ?? 0.0;

// ── Weather calculations ─────────────────────────────────────────────────────
$opts      = get_option( 'naws_settings', [] );
$temp_unit = ( $opts['temperature_unit'] ?? 'C' ) === 'F' ? '°F' : '°C';
$use_f     = $temp_unit === '°F';

$feels_like  = ( $temp_out !== null && $hum_out !== null )
    ? NAWS_Astro::feels_like( $temp_out, $hum_out, $wind )
    : null;
if ( $feels_like !== null && $use_f ) $feels_like = round( $feels_like * 9/5 + 32, 1 );

$dew_point   = ( $temp_out !== null && $hum_out !== null )
    ? NAWS_Astro::dew_point( $temp_out, $hum_out )
    : null;
if ( $dew_point !== null && $use_f ) $dew_point = round( $dew_point * 9/5 + 32, 1 );

// Heat index threshold: 25°C = 77°F
$hi_threshold = $use_f ? 77 : 25;
$heat_index  = ( $temp_out !== null && $temp_out >= 25 && $hum_out !== null )
    ? NAWS_Astro::heat_index( $temp_out, $hum_out )
    : null;
if ( $heat_index !== null && $use_f ) $heat_index = round( $heat_index * 9/5 + 32, 1 );

// ── Astronomy ────────────────────────────────────────────────────────────────
$coords   = NAWS_Astro::get_coords();
$sun      = $coords ? NAWS_Astro::sun_times( $coords['lat'], $coords['lng'] ) : null;
$moon     = NAWS_Astro::moon_data();
$moonrs   = $coords ? NAWS_Astro::moon_rise_set( $coords['lat'], $coords['lng'] ) : null;
$supermoon  = NAWS_Astro::next_supermoon();
$lunar_ecl  = NAWS_Astro::next_lunar_eclipse();

// ── Build rows ───────────────────────────────────────────────────────────────
$rows = [];

if ( $feels_like !== null ) {
    $rows[] = [
        'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z"/></svg>',
        'label' => naws__( 'infobar_feels_like' ),
        'value' => $feels_like . ' ' . $temp_unit,
        'group' => 'weather',
    ];
}

if ( $dew_point !== null ) {
    $rows[] = [
        'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>',
        'label' => naws__( 'infobar_dew_point' ),
        'value' => $dew_point . ' ' . $temp_unit,
        'group' => 'weather',
    ];
}

if ( $heat_index !== null ) {
    $rows[] = [
        'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>',
        'label' => naws__( 'infobar_heat_index' ),
        'value' => $heat_index . ' ' . $temp_unit,
        'group' => 'weather',
    ];
}

if ( $sun ) {
    $rows[] = [
        'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 18a5 5 0 0 0-10 0"/><line x1="12" y1="9" x2="12" y2="2"/><line x1="4.22" y1="10.22" x2="5.64" y2="11.64"/><line x1="1" y1="18" x2="3" y2="18"/><line x1="21" y1="18" x2="23" y2="18"/><line x1="18.36" y1="11.64" x2="19.78" y2="10.22"/><line x1="23" y1="22" x2="1" y2="22"/><polyline points="16 5 12 9 8 5"/></svg>',
        'label' => naws__( 'infobar_sunrise' ),
        'value' => $sun['rise'] . ' ' . naws__( 'time_suffix' ),
        'group' => 'astro',
    ];
    $rows[] = [
        'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 18a5 5 0 0 0-10 0"/><line x1="12" y1="9" x2="12" y2="2"/><line x1="4.22" y1="10.22" x2="5.64" y2="11.64"/><line x1="1" y1="18" x2="3" y2="18"/><line x1="21" y1="18" x2="23" y2="18"/><line x1="18.36" y1="11.64" x2="19.78" y2="10.22"/><line x1="23" y1="22" x2="1" y2="22"/><polyline points="8 5 12 9 16 5"/></svg>',
        'label' => naws__( 'infobar_sunset' ),
        'value' => $sun['set'] . ' ' . naws__( 'time_suffix' ),
        'group' => 'astro',
    ];
}

$rows[] = [
    'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>',
    'label' => naws__( 'infobar_moon_phase' ),
    'value' => $moon['name'] . ' – ' . $moon['phase_pct'] . '%',
    'group' => 'astro',
];

$rows[] = [
    'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/></svg>',
    'label' => naws__( 'infobar_next_full' ),
    'value' => $moon['next_full'],
    'group' => 'astro',
];

if ( $moonrs ) {
    $rows[] = [
        'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/><polyline points="12 2 12 6"/><polyline points="9 4 12 2 15 4"/></svg>',
        'label' => naws__( 'infobar_moonrise' ),
        'value' => $moonrs['rise'] . ' ' . naws__( 'time_suffix' ),
        'group' => 'astro',
    ];
    $rows[] = [
        'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/><polyline points="9 22 12 18 15 22"/></svg>',
        'label' => naws__( 'infobar_moonset' ),
        'value' => $moonrs['set'] . ' ' . naws__( 'time_suffix' ),
        'group' => 'astro',
    ];
}

if ( $supermoon ) {
    $rows[] = [
        'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="1" x2="12" y2="4"/><line x1="12" y1="20" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="6.34" y2="6.34"/><line x1="17.66" y1="17.66" x2="19.78" y2="19.78"/></svg>',
        'label' => naws__( 'infobar_supermoon' ),
        'value' => $supermoon['date'] . ' (' . number_format( $supermoon['distance_km'], 0, ',', '.' ) . ' km)',
        'group' => 'astro',
    ];
}

if ( $lunar_ecl ) {
    $rows[] = [
        'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 1 0 18" fill="currentColor" opacity="0.3"/></svg>',
        'label' => naws__( 'infobar_lunar_ecl' ),
        'value' => naws__( 'eclipse_' . $lunar_ecl['type'] ) . ' – ' . $lunar_ecl['date'],
        'group' => 'astro',
    ];
}

?>
<div class="naws-wrap naws-infobar">
    <div class="naws-infobar-grid">
        <?php foreach ( $rows as $row ) : ?>
        <div class="naws-infobar-item naws-ib-<?php echo esc_attr( $row['group'] ); ?>">
            <span class="naws-ib-icon">
                <?php echo wp_kses( $row['icon'], naws_svg_kses_args() ); ?>
            </span>
            <span class="naws-ib-text">
                <span class="naws-ib-label"><?php echo esc_html( $row['label'] ); ?></span>
                <span class="naws-ib-value"><?php echo esc_html( $row['value'] ); ?></span>
            </span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
