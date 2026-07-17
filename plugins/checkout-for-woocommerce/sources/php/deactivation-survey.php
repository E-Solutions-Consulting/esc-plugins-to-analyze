<?php
/**
 * Legacy path compatibility shim.
 *
 * Procedural PHP moved from sources/php/ to functions/. This file remains so
 * sites upgrading from builds that still require sources/php/deactivation-survey.php
 * do not fatal when WordPress runs the previous plugin code during the same
 * update.php request (https://core.trac.wordpress.org/ticket/57500).
 *
 * @deprecated remove once releases shipping only the functions/ path are no longer upgraded from
 */
if ( ! defined( 'CFW_PATH_BASE' ) ) {
	return;
}

require_once CFW_PATH_BASE . 'functions/deactivation-survey.php';
