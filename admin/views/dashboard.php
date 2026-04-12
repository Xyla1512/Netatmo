<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
// Module type icons for accordion headers
$module_type_icons = [
    'NAMain'   => '🏠',
    'NAModule1'=> '🌿',
    'NAModule2'=> '💨',
    'NAModule3'=> '🌧️',
    'NAModule4'=> '🛏️',
];
$module_type_colors = [
    'NAMain'   => '#2271b1',
    'NAModule1'=> '#10b981',
    'NAModule2'=> '#8b5cf6',
    'NAModule3'=> '#0ea5e9',
    'NAModule4'=> '#f59e0b',
];
?>

<div class="wrap naws-admin-wrap">

    <div class="naws-dashboard-header">
        <div class="naws-dashboard-title">
            <span class="naws-title-icon">🌤️</span>
            <div>
                <h1><?php naws_e( 'plugin_name' ); ?></h1>
                <p class="naws-subtitle"><?php naws_e( 'dashboard_subtitle' ); ?></p>
            </div>
        </div>
        <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
            <?php wp_nonce_field('naws_manual_sync'); ?>
            <input type="hidden" name="action" value="naws_manual_sync">
            <button type="submit" class="naws-btn-sync">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
                <?php naws_e( 'sync_now' ); ?>
            </button>
        </form>
    </div>

    <?php
    $naws_notice_valid = isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'naws_notice' );
    ?>
    <?php if ( $naws_notice_valid && isset( $_GET['synced'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php naws_e( 'synced_ok' ); ?></p></div>
    <?php endif; ?>
    <?php if ( $naws_notice_valid && isset( $_GET['error'] ) ) : ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['error'] ) ) ); ?></p></div>
    <?php endif; ?>

    <!-- ═══════════════════════════ STATS ROW ═══════════════════════════ -->
    <?php
    $sizes       = NAWS_Database::get_table_sizes_mb();
    $raw_mb      = $sizes[ NAWS_TABLE_READINGS ] ?? 0;
    $daily_mb    = $sizes[ NAWS_TABLE_DAILY ]    ?? 0;
    $total_mb    = round( $raw_mb + $daily_mb, 2 );
    $daily_count = NAWS_Database::count_daily_summaries();
    $range       = NAWS_Database::get_data_range();
    $daily_range = NAWS_Database::get_daily_data_range();

    // SVG icons (monochrome, stroke-based)
    $svg = [
        'modules'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/></svg>',
        'readings' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>',
        'db'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>',
        'daily'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><polyline points="8 14 10 16 14 12"/></svg>',
        'oldest'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
        'history'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.55"/></svg>',
        'lastsync' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        'nextsync' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>',
    ];
    ?>
    <div class="naws-stats-row">

        <div class="naws-stat-card">
            <div class="naws-stat-icon-wrap naws-stat-color-blue"><?php echo naws_kses_svg( $svg['modules'] ); ?></div>
            <div class="naws-stat-body">
                <div class="naws-stat-value"><?php echo esc_html( count($modules) ); ?></div>
                <div class="naws-stat-label"><?php naws_e( 'menu_modules' ); ?></div>
            </div>
        </div>

        <div class="naws-stat-card">
            <div class="naws-stat-icon-wrap naws-stat-color-green"><?php echo naws_kses_svg( $svg['readings'] ); ?></div>
            <div class="naws-stat-body">
                <div class="naws-stat-value"><?php echo esc_html( number_format($total, 0, ',', '.') ); ?></div>
                <div class="naws-stat-label"><?php naws_e( 'current_readings' ); ?></div>
            </div>
        </div>

        <div class="naws-stat-card">
            <div class="naws-stat-icon-wrap naws-stat-color-purple"><?php echo naws_kses_svg( $svg['db'] ); ?></div>
            <div class="naws-stat-body">
                <div class="naws-stat-value"><?php echo esc_html( $total_mb ); ?> <span class="naws-stat-unit">MB</span></div>
                <div class="naws-stat-label">DB</div>
                <div class="naws-stat-sub">Raw <?php echo esc_html($raw_mb); ?> · Hist <?php echo esc_html($daily_mb); ?> MB</div>
            </div>
        </div>

        <div class="naws-stat-card">
            <div class="naws-stat-icon-wrap naws-stat-color-teal"><?php echo naws_kses_svg( $svg['daily'] ); ?></div>
            <div class="naws-stat-body">
                <div class="naws-stat-value"><?php echo esc_html( number_format($daily_count, 0, ',', '.') ); ?></div>
                <div class="naws-stat-label"><?php naws_e( 'daily_summary' ); ?></div>
            </div>
        </div>

        <?php if ( $range && $range['date_begin'] ) : ?>
        <div class="naws-stat-card">
            <div class="naws-stat-icon-wrap naws-stat-color-slate"><?php echo naws_kses_svg( $svg['oldest'] ); ?></div>
            <div class="naws-stat-body">
                <div class="naws-stat-value naws-stat-value--date"><?php echo esc_html( wp_date('d.m.Y', $range['date_begin']) ); ?></div>
                <div class="naws-stat-label"><?php naws_e( 'date' ); ?></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( $daily_range && $daily_range['date_begin'] ) : ?>
        <div class="naws-stat-card">
            <div class="naws-stat-icon-wrap naws-stat-color-slate"><?php echo naws_kses_svg( $svg['history'] ); ?></div>
            <div class="naws-stat-body">
                <div class="naws-stat-value naws-stat-value--date"><?php echo esc_html( $daily_range['date_begin'] ); ?></div>
                <div class="naws-stat-label"><?php naws_e( 'history' ); ?></div>
            </div>
        </div>
        <?php endif; ?>

        <div class="naws-stat-card">
            <div class="naws-stat-icon-wrap naws-stat-color-orange"><?php echo naws_kses_svg( $svg['lastsync'] ); ?></div>
            <div class="naws-stat-body">
                <div class="naws-stat-value naws-stat-value--date"><?php echo $last_sync ? esc_html( wp_date('H:i', $last_sync) ) : '—'; ?></div>
                <div class="naws-stat-label"><?php naws_e( 'recent_sync' ); ?></div>
                <div class="naws-stat-sub"><?php echo $last_sync ? esc_html( human_time_diff($last_sync) ) : ''; ?></div>
            </div>
        </div>

        <div class="naws-stat-card">
            <div class="naws-stat-icon-wrap naws-stat-color-blue"><?php echo naws_kses_svg( $svg['nextsync'] ); ?></div>
            <div class="naws-stat-body">
                <div class="naws-stat-value naws-stat-value--date"><?php echo $next_run ? esc_html( wp_date('H:i', $next_run) ) : '—'; ?></div>
                <div class="naws-stat-label"><?php naws_e( 'next_run' ); ?></div>
                <div class="naws-stat-sub"><?php echo $next_run ? esc_html( 'in ' . human_time_diff($next_run) ) : ''; ?></div>
            </div>
        </div>

        <?php
        // Health status card
        $health       = NAWS_Cron::get_health_status();
        $health_color = 'green';
        $health_icon  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
        if ( $health['status'] === 'warning' ) {
            $health_color = 'orange';
            $health_icon  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
        } elseif ( $health['status'] === 'error' ) {
            $health_color = 'red';
            $health_icon  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
        }
        // Error log counter
        $error_counts = NAWS_Logger::count_by_level();
        $recent_errors = NAWS_Logger::count_recent_errors( 60 );
        ?>
        <div class="naws-stat-card">
            <div class="naws-stat-icon-wrap naws-stat-color-<?php echo esc_attr( $health_color ); ?>"><?php echo naws_kses_svg( $health_icon ); ?></div>
            <div class="naws-stat-body">
                <div class="naws-stat-value naws-stat-value--date" style="font-size:0.8rem;"><?php echo esc_html( $health['message'] ); ?></div>
                <div class="naws-stat-label">Health</div>
                <?php if ( $recent_errors > 0 ) : ?>
                <div class="naws-stat-sub" style="color:#ef4444;"><?php echo esc_html( sprintf( naws__( 'errors_last_hour' ), $recent_errors ) ); ?></div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- ═══════════════════════════ MAIN CONTENT ═══════════════════════════ -->
    <div class="naws-admin-two-col">

        <!-- ── Left: Current Readings (Accordion) ── -->
        <div>
            <div class="naws-section-label">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <?php naws_e( 'current_readings' ); ?>
                <span class="naws-live-dot"></span>
                <span style="font-size:0.7rem; color:#646970;"><?php echo $last_sync ? esc_html( 'Live · ' . wp_date('H:i', $last_sync) ) : ''; ?></span>
            </div>

            <?php if ( empty( $modules ) ) : ?>
                <div class="naws-admin-panel">
                    <div class="naws-empty-state">
                        <div style="font-size:2.5rem; margin-bottom:0.75rem;">📡</div>
                        <p style="margin:0 0 1rem;"><?php naws_e( 'no_modules' ); ?></p>
                        <a href="<?php echo esc_url( admin_url('admin.php?page=naws-settings') ); ?>" class="button button-primary">
                            <?php naws_e( 'go_to_settings' ); ?>
                        </a>
                    </div>
                </div>
            <?php else : ?>

                <div class="naws-accordion" id="naws-modules-accordion">
                    <?php foreach ( $modules as $i => $module ) :
                        $mod_readings = $readings_by_module[ $module['module_id'] ] ?? [];
                        $has_data     = ! empty( $mod_readings );
                        $type         = $module['module_type'] ?? '';
                        $type_icon    = $module_type_icons[ $type ] ?? '📦';
                        $type_color   = $module_type_colors[ $type ] ?? '#646970';
                        $type_label   = NAWS_Helpers::module_type_label( $type );
                        $is_open      = ( $i === 0 );

                        // Build a short summary for the collapsed state
                        $summary_parts = [];
                        if ( $has_data ) {
                            if ( isset( $mod_readings['Temperature'] ) ) {
                                $summary_parts[] = '🌡️ ' . NAWS_Helpers::format_value('Temperature', $mod_readings['Temperature']) . ' °C';
                            }
                            if ( isset( $mod_readings['Humidity'] ) ) {
                                $summary_parts[] = '💧 ' . NAWS_Helpers::format_value('Humidity', $mod_readings['Humidity']) . ' %';
                            }
                            if ( isset( $mod_readings['WindSpeed'] ) ) {
                                $summary_parts[] = '💨 ' . NAWS_Helpers::format_value('WindSpeed', $mod_readings['WindSpeed']) . ' km/h';
                            }
                            if ( isset( $mod_readings['Rain'] ) ) {
                                $summary_parts[] = '🌧️ ' . NAWS_Helpers::format_value('Rain', $mod_readings['Rain']) . ' mm';
                            }
                        }
                    ?>
                    <div class="naws-accordion-item <?php echo $is_open ? 'is-open' : ''; ?>">
                        <button type="button" class="naws-accordion-trigger" aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>">
                            <div class="naws-acc-left">
                                <span class="naws-acc-module-icon" style="background:<?php echo esc_attr($type_color); ?>15; color:<?php echo esc_attr($type_color); ?>;">
                                    <?php echo naws_kses_svg( $type_icon ); ?>
                                </span>
                                <div class="naws-acc-titles">
                                    <span class="naws-acc-name"><?php echo esc_html( $module['module_name'] ); ?></span>
                                    <span class="naws-acc-meta">
                                        <span class="naws-module-type-badge" style="background:<?php echo esc_attr($type_color); ?>18; color:<?php echo esc_attr($type_color); ?>;"><?php echo esc_html($type_label); ?></span>
                                        <?php if ( ! empty( $summary_parts ) ) : ?>
                                            <span class="naws-acc-summary"><?php echo esc_html( implode(' · ', $summary_parts) ); ?></span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="naws-acc-right">
                                <span class="naws-status-dot <?php echo $has_data ? 'is-active' : 'is-empty'; ?>"></span>
                                <svg class="naws-acc-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                            </div>
                        </button>
                        <div class="naws-accordion-body">
                            <div class="naws-accordion-body-inner">
                                <?php if ( ! $has_data ) : ?>
                                    <div class="naws-no-data-row">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                        <?php naws_e( 'no_data' ); ?>
                                    </div>
                                <?php else : ?>
                                    <table class="naws-readings-table">
                                        <thead>
                                            <tr>
                                                <th><?php naws_e( 'value' ); ?></th>
                                                <th><?php naws_e( 'value' ); ?></th>
                                                <th><?php naws_e( 'unit' ); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            // These Netatmo fields are Unix timestamps, not sensor values.
                                            // Skip them in the dashboard display even if old data exists in DB.
                                            $skip_display = [
                                                'time_utc', 'date_min_temp', 'date_max_temp',
                                                'date_min_pressure', 'date_max_pressure',
                                                'date_max_wind_str', 'date_max_gust',
                                            ];
                                            foreach ( $mod_readings as $param => $val ) :
                                                if ( in_array( $param, $skip_display, true ) ) continue;
                                            ?>
                                            <tr>
                                                <td class="naws-param-cell"><?php echo naws_kses_svg( NAWS_Helpers::get_icon( $param ) ); ?> <?php echo esc_html( NAWS_Helpers::get_label($param) ); ?></td>
                                                <td class="naws-value-cell"><strong><?php echo esc_html( NAWS_Helpers::format_value($param, $val) ); ?></strong></td>
                                                <td class="naws-unit-cell"><?php echo esc_html( NAWS_Helpers::get_unit($param) ); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>

            <?php if ( ! empty( $last_error ) ) : ?>
                <div class="notice notice-error" style="margin-top:1rem;">
                    <p><strong><?php naws_e( 'error_occurred' ); ?></strong> <?php echo esc_html( $last_error ); ?></p>
                </div>
            <?php endif; ?>

            <!-- Daily Summary (below accordion) -->
            <div class="naws-section-label" style="margin-top:1.5rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <?php naws_e( 'daily_summary' ); ?>
            </div>
            <div class="naws-admin-panel">
                <div class="naws-daily-summary-body">
                    <div class="naws-daily-info">
                        <p><?php naws_e( 'cron_daily_desc' ); ?></p>
                        <p class="naws-daily-next">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <strong><?php naws_e( 'next_run' ); ?></strong>
                            <?php
                            $next_daily = NAWS_Cron::get_next_daily_run();
                            echo $next_daily
                                 ? esc_html( wp_date('d.m.Y H:i', $next_daily) . ' — in ' . human_time_diff($next_daily) )
                                 : esc_html( naws__( 'not_scheduled' ) );
                            ?>
                        </p>
                    </div>
                    <div class="naws-daily-controls">
                        <input type="date" id="naws-daily-date"
                               value="<?php echo esc_attr(wp_date('Y-m-d', strtotime('yesterday'))); ?>"
                               max="<?php echo esc_attr(wp_date('Y-m-d', strtotime('yesterday'))); ?>"
                               class="naws-date-input">
                        <button id="naws-run-daily-btn" class="button button-primary">
                            <?php naws_e( 'daily_summary' ); ?>
                        </button>
                        <span id="naws-daily-result" class="naws-daily-result"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Right: Sidebar ── -->
        <div class="naws-sidebar">

            <!-- Quick Actions -->
            <div class="naws-section-label">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                <?php naws_e( 'menu_dashboard' ); ?>
            </div>
            <div class="naws-admin-panel" style="margin-bottom:1.5rem;">
                <div class="naws-quick-links">
                    <a href="<?php echo esc_url( admin_url('admin.php?page=naws-settings') ); ?>" class="naws-quick-link">
                        <span class="naws-ql-icon" style="background:#eff6ff; color:#2563eb;">⚙️</span>
                        <span><?php naws_e( 'settings_oauth' ); ?></span>
                        <svg class="naws-ql-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>
                    <a href="<?php echo esc_url( admin_url('admin.php?page=naws-import') ); ?>" class="naws-quick-link">
                        <span class="naws-ql-icon" style="background:#f0fdf4; color:#16a34a;">📥</span>
                        <span><?php naws_e( 'menu_import' ); ?></span>
                        <svg class="naws-ql-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>

                    <a href="<?php echo esc_url( admin_url('admin.php?page=naws-cron-log') ); ?>" class="naws-quick-link">
                        <span class="naws-ql-icon" style="background:#fff7ed; color:#ea580c;">📋</span>
                        <span><?php naws_e( 'menu_cron_log' ); ?></span>
                        <svg class="naws-ql-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>
                </div>
            </div>

            <!-- Cron Status -->
            <div class="naws-section-label">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <?php naws_e( 'cron_status' ); ?>
            </div>
            <div class="naws-admin-panel">
                <div class="naws-cron-grid">
                    <div class="naws-cron-item">
                        <span class="naws-cron-label"><?php naws_e( 'cron_interval' ); ?></span>
                        <span class="naws-cron-value">
                            <span class="naws-badge naws-badge-info"><?php echo esc_html( $options['cron_interval'] ?? 10 ); ?> min</span>
                        </span>
                    </div>
                    <div class="naws-cron-item">
                        <span class="naws-cron-label"><?php naws_e( 'next_run' ); ?></span>
                        <span class="naws-cron-value"><?php echo $next_run ? esc_html( wp_date('Y-m-d H:i:s', $next_run) ) : '—'; ?></span>
                    </div>
                    <div class="naws-cron-item">
                        <span class="naws-cron-label"><?php naws_e( 'recent_sync' ); ?></span>
                        <span class="naws-cron-value"><?php echo $last_sync ? esc_html( wp_date('Y-m-d H:i:s', $last_sync) ) : '—'; ?></span>
                    </div>
                    <div class="naws-cron-item">
                        <span class="naws-cron-label"><?php naws_e( 'data_retention' ); ?></span>
                        <span class="naws-cron-value"><?php echo esc_html( ( $options['data_retention'] ?? 365 )  ); ?></span>
                    </div>
                </div>
            </div>

        </div><!-- /.naws-sidebar -->
    </div><!-- /.naws-admin-two-col -->
</div><!-- /.naws-admin-wrap -->

<?php
ob_start();
?>
document.addEventListener('DOMContentLoaded', function () {

    // Accordion logic
    document.querySelectorAll('.naws-accordion-trigger').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var item = this.closest('.naws-accordion-item');
            var isOpen = item.classList.contains('is-open');
            document.querySelectorAll('.naws-accordion-item').forEach(function (el) {
                el.classList.remove('is-open');
                el.querySelector('.naws-accordion-trigger').setAttribute('aria-expanded', 'false');
            });
            if (!isOpen) {
                item.classList.add('is-open');
                btn.setAttribute('aria-expanded', 'true');
            }
        });
    });

    // ── Manual daily summary button ──────────────────────────────────────
    var dailyBtn    = document.getElementById('naws-run-daily-btn');
    var dailyDate   = document.getElementById('naws-daily-date');
    var dailyResult = document.getElementById('naws-daily-result');

    if (dailyBtn) {
        dailyBtn.addEventListener('click', function () {
            var date = dailyDate ? dailyDate.value : '';
            dailyBtn.disabled = true;
            dailyBtn.textContent = nawsAdmin.strings.importing;
            dailyResult.textContent = '';
            dailyResult.style.color = '';

            var body = 'action=naws_run_daily_summary'
                     + '&nonce=' + encodeURIComponent('<?php echo esc_js( wp_create_nonce('naws_admin_nonce') ); ?>')
                     + '&date='  + encodeURIComponent(date);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo esc_js( admin_url('admin-ajax.php') ); ?>');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function () {
                dailyBtn.disabled = false;
                dailyBtn.textContent = <?php echo wp_json_encode( naws__( 'daily_summary' ) ); ?>;
                try {
                    var r = JSON.parse(xhr.responseText);
                    if (r.success) {
                        dailyResult.textContent = '✓ ' + r.data.message;
                        dailyResult.style.color = '#1a7a50';
                    } else {
                        dailyResult.textContent = '✗ ' + (r.data ? r.data.message : nawsAdmin.strings.error);
                        dailyResult.style.color = '#b32d2e';
                    }
                } catch (e) {
                    dailyResult.textContent = '✗ ' + nawsAdmin.strings.error;
                    dailyResult.style.color = '#b32d2e';
                }
            };
            xhr.onerror = function () {
                dailyBtn.disabled = false;
                dailyBtn.textContent = <?php echo wp_json_encode( naws__( 'daily_summary' ) ); ?>;
                dailyResult.textContent = '✗ ' + nawsAdmin.strings.error;
                dailyResult.style.color = '#b32d2e';
            };
            xhr.send(body);
        });
    }

});
<?php
wp_add_inline_script( 'naws-admin', ob_get_clean() );
?>
