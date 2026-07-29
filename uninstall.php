<?php
/**
 * Uninstall cleanup for Kosher Typesense Search.
 *
 * @package kosher-typesense-search
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

delete_option('kosher_typesense_analytics_db_version');

