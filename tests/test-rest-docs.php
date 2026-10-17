<?php
/**
 * Die REST-API-Seite im Backend: was sie ueber den Schluessel sagt und
 * ob ihr PHP-Beispiel dort laeuft, wo Nutzer es einsetzen.
 *
 * Seit 2.1.4. Drei Dinge waren falsch: der Hinweis unter der Base-URL
 * erlaubte den Schluessel noch als Query-Parameter, obwohl authenticate()
 * ihn dort mit 401 ablehnt; die Beispiele fuer JavaScript und Chart.js
 * legten den Schluessel ohne Warnung in den Browser; und das PHP-Beispiel
 * arbeitete mit `global $base, $key` und einem echo beim Laden. In einem
 * Snippet-Plugin (Code Snippets, WPCode) laeuft der Code in einer Funktion,
 * `global` findet die beiden Variablen dort nicht, und die Anfrage ging an
 * eine leere Adresse. Das neue Beispiel ist ein Shortcode; dieser Test
 * schneidet ihn aus der Seite, wie ein Nutzer ihn kopiert, und fuehrt ihn
 * gegen nachgebildete WordPress-Funktionen aus.
 *
 *   php tests/test-rest-docs.php
 *
 * @package NAWS
 */
$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}
$view = (string) file_get_contents( dirname( __DIR__ ) . '/admin/views/rest-api-docs.php' );

echo "\nWas die Seite ueber den Schluessel sagt\n" . str_repeat( '-', 74 ) . "\n";
check( 'kein "header or query parameter" mehr', str_contains( $view, 'header or query parameter' ), false );
check( 'Base-URL-Hinweis nennt den Header',     str_contains( $view, 'Send your API key in the X-NAWS-Key header' ), true );
check( 'Warnung ueber den Beispielen',          str_contains( $view, "not from a visitor\\'s browser" ), true );
check( 'Hinweis bei JavaScript und Chart.js',   substr_count( $view, 'the key is visible in the page source' ), 2 );
check( 'Hinweis steht im JS-Reiter',            (bool) preg_match( '/id="ex-js">\n<p class="naws-hint">[^\n]*the key is visible/', $view ), true );
check( 'Hinweis steht im Chart.js-Reiter',      (bool) preg_match( '/id="ex-google">\n<p class="naws-hint">[^\n]*the key is visible/', $view ), true );
check( 'kein global $base mehr',                str_contains( $view, 'global $base' ), false );
check( 'kein curl_init im PHP-Beispiel',        str_contains( $view, 'curl_init' ), false );

echo "\nDas PHP-Beispiel, wie ein Nutzer es kopiert\n" . str_repeat( '-', 74 ) . "\n";
check( 'Reiter heisst PHP / Snippet',           str_contains( $view, 'data-tab="ex-php">PHP / Snippet</button>' ), true );
check( 'Kopieren-Knopf im Reiter',              (bool) preg_match( '/id="ex-php">.*?id="naws-copy-snippet".*?id="naws-snippet-code"/s', $view ), true );
check( 'Knopf kopiert den reinen Text',         str_contains( $view, 'navigator.clipboard.writeText(code.textContent)' ), true );
preg_match( '/id="ex-php">.*?<div class="naws-code-block" id="naws-snippet-code">(.*?)<\/div>/s', $view, $m );
check( 'Codeblock gefunden', isset( $m[1] ), true );
$code = $m[1] ?? '';
$code = str_replace( "<?php echo esc_html( \$base_url ); ?>", 'https://wetter.example/wp-json/naws/v1', $code );
$code = str_replace( "<?php echo esc_html( \$api_key ?: 'DEIN_API_KEY' ); ?>", 'naws_test', $code );
check( 'keine PHP-Ausgabe mehr im Code', str_contains( $code, '<?php' ), false );
$code = html_entity_decode( strip_tags( $code ), ENT_QUOTES, 'UTF-8' );
check( 'ohne oeffnendes <?php (Snippet-Plugin)', str_starts_with( ltrim( $code ), '<?php' ), false );

// Wie ein Snippet-Plugin: der Code laeuft innerhalb einer Funktion.
$GLOBALS['sc']   = [];
$GLOBALS['tr']   = [];
$GLOBALS['http'] = [];
function add_shortcode( $tag, $cb ) { $GLOBALS['sc'][ $tag ] = $cb; }
function shortcode_atts( $pairs, $atts ) { return array_merge( $pairs, array_intersect_key( (array) $atts, $pairs ) ); }
function get_transient( $k ) { return $GLOBALS['tr'][ $k ] ?? false; }
function set_transient( $k, $v, $t ) { $GLOBALS['tr'][ $k ] = $v; return true; }
function wp_remote_get( $url, $args ) { $GLOBALS['http'][] = [ $url, $args ]; return $GLOBALS['answer']; }
function is_wp_error( $x ) { return $x === 'error'; }
function wp_remote_retrieve_response_code( $r ) { return $r['code']; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function number_format_i18n( $n, $d ) { return number_format( $n, $d, ',', '.' ); }
const MINUTE_IN_SECONDS = 60;

$lint = proc_open( [ PHP_BINARY, '-l' ], [ [ 'pipe', 'r' ], [ 'pipe', 'w' ], [ 'pipe', 'w' ] ], $pipes );
fwrite( $pipes[0], "<?php\n" . $code ); fclose( $pipes[0] );
$lint_out = stream_get_contents( $pipes[1] ); proc_close( $lint );
check( 'php -l: keine Syntaxfehler', str_contains( $lint_out, 'No syntax errors' ), true );

// eval ist hier Absicht und unbedenklich: der Code stammt aus der Datei im
// Repo, nicht aus einer Eingabe, und der Test soll ihn so ausfuehren wie ein
// Snippet-Plugin, naemlich innerhalb einer Funktion.
( function () use ( $code ) { eval( $code ); } )();
check( 'Shortcode registriert', isset( $GLOBALS['sc']['wetter_aktuell'] ), true );
$cb = $GLOBALS['sc']['wetter_aktuell'] ?? fn() => '';

$GLOBALS['answer'] = [ 'code' => 200, 'body' => json_encode( [ 'readings' => [
    [ 'module_name' => 'Außen', 'parameter' => 'Temperature', 'value' => 18.46, 'unit' => '°C' ],
    [ 'module_name' => 'Innen <b>', 'parameter' => 'CO2', 'value' => 612, 'unit' => 'ppm' ],
] ] ) ];
$html = $cb( [] );
check( 'Anfrage an /current der Base-URL', $GLOBALS['http'][0][0] ?? '', 'https://wetter.example/wp-json/naws/v1/current' );
check( 'Schluessel im Header',            $GLOBALS['http'][0][1]['headers']['X-NAWS-Key'] ?? '', 'naws_test' );
check( 'Schluessel nicht in der URL',     str_contains( $GLOBALS['http'][0][0] ?? '', 'naws_test' ), false );
check( 'Ausgabe: Liste mit zwei Werten',  substr_count( $html, '<li>' ), 2 );
check( 'Ausgabe: Dezimalkomma',           str_contains( $html, '<li><strong>Außen – Temperature:</strong> 18,5 °C</li>' ), true );
check( 'Ausgabe: Modulname escaped',      str_contains( $html, 'Innen &lt;b&gt;' ), true );

$cb( [] );
check( 'zweiter Aufruf aus dem Zwischenspeicher', count( $GLOBALS['http'] ), 1 );
$one = $cb( [ 'modul' => 'Außen' ] );
check( 'modul="Außen" zeigt nur dieses Modul', substr_count( $one, '<li>' ), 1 );

$GLOBALS['tr'] = [];
$GLOBALS['answer'] = [ 'code' => 401, 'body' => '{}' ];
check( 'Fehler: freundlicher Text', $cb( [] ), '<p>Wetterdaten sind gerade nicht verfügbar.</p>' );
check( 'Fehler wird nicht zwischengespeichert', $GLOBALS['tr'], [] );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
