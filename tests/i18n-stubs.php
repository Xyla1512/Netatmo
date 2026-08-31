<?php
/**
 * Gettext stubs for the standalone tests.
 *
 * The tests run without a WordPress bootstrap, so the translation functions
 * the plugin now calls do not exist. Each returns its input unchanged, which
 * is what an untranslated locale does anyway — the tests care about the
 * values that flow through, not about translation.
 *
 * Every definition is guarded, so a test that needs a translation to differ
 * from its original can define that function itself before loading this file
 * and keep the rest.
 *
 * Loading this also pulls in the real naws_label() table, so a test that
 * reaches a runtime-resolved label exercises the same lookup the plugin uses.
 *
 * @package NAWS
 */

if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = null ) { return $text; }
}
if ( ! function_exists( '_x' ) ) {
    function _x( $text, $context, $domain = null ) { return $text; }
}
if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( $text, $domain = null ) { return $text; }
}
if ( ! function_exists( 'esc_attr__' ) ) {
    function esc_attr__( $text, $domain = null ) { return $text; }
}
if ( ! function_exists( 'esc_html_e' ) ) {
    function esc_html_e( $text, $domain = null ) { echo $text; }
}
if ( ! function_exists( 'esc_attr_e' ) ) {
    function esc_attr_e( $text, $domain = null ) { echo $text; }
}
if ( ! function_exists( '_n' ) ) {
    function _n( $single, $plural, $number, $domain = null ) { return 1 === (int) $number ? $single : $plural; }
}

require_once __DIR__ . '/../includes/class-naws-labels.php';
