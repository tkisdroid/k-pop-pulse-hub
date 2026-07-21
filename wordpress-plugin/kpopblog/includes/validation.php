<?php
/**
 * Shared validation helpers.
 *
 * @package KpopBlog
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function kpopblog_is_strict_iso8601_date( $value ) {
	$value = (string) $value;
	$date_pattern = '/^\d{4}-\d{2}-\d{2}\z/';
	$datetime_pattern = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2}(?:\.\d{1,6})?)?(?:Z|[+-]\d{2}:\d{2})\z/';
	if ( ! preg_match( $date_pattern, $value ) && ! preg_match( $datetime_pattern, $value ) ) {
		return false;
	}
	$parsed = date_parse( $value );
	return 0 === $parsed['error_count'] && 0 === $parsed['warning_count'];
}
