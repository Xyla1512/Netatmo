<?php
/**
 * Prueft, dass Aufbau-Animationen erst laufen, wenn der Besucher sie sieht:
 * die Diagramme von [naws_live] und [naws_history] und die Kacheln warten
 * auf NAWS_whenInView (frontend.js), nach derselben Regel wie die Heatmap.
 *
 *   php tests/test-reveal-in-view.php
 *
 * @package NAWS
 */
$PLUGIN = dirname( __DIR__ ) . '/';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

$front   = (string) file_get_contents( $PLUGIN . 'assets/js/frontend.js' );
$live    = (string) file_get_contents( $PLUGIN . 'assets/js/live-boot.js' );
$history = (string) file_get_contents( $PLUGIN . 'assets/js/history-boot.js' );
$css     = (string) file_get_contents( $PLUGIN . 'assets/css/frontend.css' );

echo "\nfrontend.js: NAWS_whenInView\n" . str_repeat( '-', 74 ) . "\n";
preg_match( '/window\.NAWS_whenInView = function\(el, fn\) \{(.*?)\n    \};/s', $front, $m );
$fn = $m[1] ?? '';
check( 'Hilfsfunktion vorhanden',                          $fn !== '', true );
check( 'wartet, bis die Oberkante die Bildschirmmitte erreicht', str_contains( $fn, 'r.top > window.innerHeight / 2' ), true );
check( 'oder bis das Seitenende erreicht ist',             str_contains( $fn, 'scrollHeight' ), true );
check( 'unsichtbar (Reiter, Hoehe 0) zaehlt nicht',         str_contains( $fn, 'r.height === 0' ), true );
check( 'prueft beim Scrollen und bei Groessenaenderung',    str_contains( $fn, "addEventListener('scroll'" ) && str_contains( $fn, "addEventListener('resize'" ), true );
check( 'und wenn das Element sichtbar wird',               str_contains( $fn, 'new IntersectionObserver' ), true );
check( 'raeumt die Zuhoerer wieder ab',                    str_contains( $fn, "removeEventListener('scroll'" ) && str_contains( $fn, 'io.disconnect()' ), true );
check( 'zweiter Aufruf ersetzt nur die Funktion',          str_contains( $fn, 'if (el._nawsReveal) { el._nawsReveal = fn; return; }' ), true );
check( 'reduzierte Bewegung: sofort',                       str_contains( $fn, "prefers-reduced-motion: reduce)').matches) { fn(); return; }" ), true );

echo "\nKacheln\n" . str_repeat( '-', 74 ) . "\n";
check( 'Kacheln der Seite warten',                          str_contains( $front, 'window.NAWS_revealCards(document);' ), true );
check( 'kein eigener Observer mit 10 % mehr',              str_contains( $front, 'threshold: 0.1' ), false );
check( 'Hochzaehlen startet mit der Kachel',               (bool) preg_match( '/NAWS_revealCards = function.*?NAWS_whenInView\(group.*?animateNumber\(valueEl/s', $front ), true );
check( 'Live-Kacheln warten ebenso',                        (bool) preg_match( '/liveEl\.innerHTML=buildLive\(rows\);\s*applyCardOrder\(\);\s*window\.NAWS_revealCards\(liveEl\);/', $live ), true );
check( 'Wartestellung haelt das erste Bild',                (bool) preg_match( '/@media \(prefers-reduced-motion: no-preference\) \{\s*\.naws-card\.is-pending \{ animation-play-state: paused; \}/', $css ), true );

echo "\nDiagramme\n" . str_repeat( '-', 74 ) . "\n";
check( '[naws_live] zeichnet erst im Blick',                (bool) preg_match( '/NAWS_whenInView\(document\.getElementById\(canvasId\), function\(\)\{\s*window\.NAWS_releaseChartSpace\(document\.getElementById\(canvasId\)\);\s*renderChart\(canvasId, cfg, labels, vals, true\);/', $live ), true );
check( '[naws_live] Diagrammbereich vorher sichtbar',       (bool) preg_match( "/chartsEl\.style\.display='';.*?NAWS_whenInView/s", $live ), true );
check( '[naws_history] zeichnet erst im Blick',             (bool) preg_match( "/NAWS_whenInView\(document\.getElementById\(WID\+'-'\+def\.id\), function\(\)\{\s*window\.NAWS_releaseChartSpace\(document\.getElementById\(WID\+'-'\+def\.id\)\);\s*renderChart\(def\.id, false\);/", $history ), true );
check( 'Freigabe setzt Breite und Seitenverhaeltnis zurueck', (bool) preg_match( "/NAWS_releaseChartSpace = function\(canvas\) \{.*?canvas\.style\.width = '';\s*canvas\.style\.aspectRatio = '';/s", $front ), true );
check( 'Platz wird vorab gehalten (volle Breite, Seitenverhaeltnis)', str_contains( $front, "canvas.style.width = '100%';" ) && str_contains( $front, "canvas.style.aspectRatio = canvas.width + ' / ' + canvas.height;" ), true );
check( '[naws_live] braucht keinen (feste Hoehe 110)',     str_contains( $live, 'NAWS_holdChartSpace' ), false );
check( '[naws_history] haelt den Platz',                    str_contains( $history, "window.NAWS_holdChartSpace(document.getElementById(WID+'-'+def.id));" ), true );
check( '[naws_history] kein sofortiges Zeichnen mehr',      (bool) preg_match( "/forEach\(function\(def\)\{\s*renderChart\(def\.id, false\);/", $history ), false );
check( 'beide Skripte haengen an frontend.js',              (bool) preg_match( "/'naws-live-boot',.*?\[ 'naws-frontend' \]/s", (string) file_get_contents( $PLUGIN . 'includes/class-naws-shortcodes.php' ) )
    && (bool) preg_match( "/'naws-history-boot',.*?\[ 'naws-frontend'/s", (string) file_get_contents( $PLUGIN . 'includes/class-naws-shortcodes.php' ) ), true );

echo "\nWeich statt hart\n" . str_repeat( '-', 74 ) . "\n";
preg_match( '/window\.NAWS_chartWave = function\(duration, spread\) \{(.*?)\n    \};/s', $front, $w );
$wave = $w[1] ?? '';
check( 'Welle: Verzoegerung je Punkt von links nach rechts', str_contains( $wave, 'ctx.dataIndex * spread / Math.max(1, n - 1)' ), true );
check( 'Welle nur beim ersten Aufbau',                     str_contains( $wave, "ctx.type === 'data' && !ctx.chart.\$nawsWaved" ) && str_contains( $wave, 'e.chart.$nawsWaved = true;' ), true );
check( 'Punkte steigen vom Boden der Zeichenflaeche auf',  str_contains( $wave, 'return first(ctx) ? ctx.chart.chartArea.bottom : undefined;' ), true );
check( 'Welle laeuft weich aus',                           str_contains( $wave, "easing: 'easeOutCubic'" ), true );
check( '[naws_live] nutzt die Welle',                      str_contains( $live, 'var wave = window.NAWS_chartWave(900, 700);' ) && str_contains( $live, 'animation:wave.animation, animations:wave.animations,' ), true );
check( '[naws_history] nutzt die Welle',                   str_contains( $history, 'var wave = window.NAWS_chartWave(800, 900);' ) && str_contains( $history, 'animation:wave.animation, animations:wave.animations,' ), true );
check( 'Freigabe blendet die Flaeche ein',                 (bool) preg_match( "/NAWS_releaseChartSpace = function\(canvas\) \{.*?canvas\.classList\.add\('naws-chart-reveal'\);/s", $front ), true );
check( 'CSS: Flaeche blendet weich ein',                   str_contains( $css, '.naws-chart-reveal { animation: naws-chart-in 0.9s cubic-bezier(0.22, 0.61, 0.36, 1) both; }' ), true );
check( 'CSS: Kacheln 0,8 s, weich auslaufend',             str_contains( $css, '.naws-card { animation: naws-fade-in 0.8s cubic-bezier(0.22, 0.61, 0.36, 1) both; }' ), true );
check( 'CSS: Kacheln 80 ms nacheinander',                  str_contains( $css, '.naws-card:nth-child(2)  { animation-delay: 0.12s; }' ) && str_contains( $css, '.naws-card:nth-child(12) { animation-delay: 0.92s; }' ), true );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
