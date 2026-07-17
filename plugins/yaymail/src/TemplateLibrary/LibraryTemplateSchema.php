<?php

namespace YayMail\TemplateLibrary;

use YayMail\Models\LibraryTemplateModel;

/**
 * Ensures the library templates custom table exists (e.g. before migration runs in dev).
 */
class LibraryTemplateSchema {

    /**
     * Create table via dbDelta if missing.
     *
     * @return void
     */
    public static function maybe_create_table() {
        global $wpdb;

        $table_name = LibraryTemplateModel::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
            return;
        }

        self::create_table();
    }

    /**
     * Run dbDelta for yaymail_library_templates.
     *
     * @return void
     */
    public static function create_table() {
        global $wpdb;

        $table_name      = LibraryTemplateModel::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            public_id char(36) NOT NULL,
            email_type varchar(64) NOT NULL,
            name varchar(255) NOT NULL,
            description text NULL,
            elements longtext NOT NULL,
            email_settings text NOT NULL,
            global_header_settings text NULL,
            global_footer_settings text NULL,
            position smallint(5) unsigned NOT NULL DEFAULT 100,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY public_id (public_id),
            KEY idx_email_type (email_type)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
