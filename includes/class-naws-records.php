<?php
/**
 * Records from the daily summary: the hottest day, the longest dry spell,
 * the wettest month, and what this calendar day looked like in earlier
 * years.
 *
 * The arithmetic is pure — daily rows in, numbers out — so it is tested on
 * a hand-built year. Only rows() and delta_parts() touch WordPress.
 *
 * @package NAWS
 * @since   1.9.11
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class NAWS_Records {
}
