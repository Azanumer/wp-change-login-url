<?php
/**
 * Uninstall: remove the plugin's option. Rewrite rules are flushed by
 * WordPress itself after uninstall.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('wcl_settings');
