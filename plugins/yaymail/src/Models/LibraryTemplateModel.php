<?php

namespace YayMail\Models;

use YayMail\TemplateLibrary\LibraryTemplateSchema;
use YayMail\Utils\TemplateHelpers;

/**
 * Persistence for user-saved Template Library presets.
 */
class LibraryTemplateModel {

    public const TABLE_NAME = 'yaymail_library_templates';

    public const SAVED_ID_PREFIX = 'saved_';

    /**
     * Full table name including WP prefix.
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Insert one library template row.
     *
     * @param array $row Row data.
     *
     * @return string|false public_id on success, false on failure.
     */
    public static function create( $row ) {
        self::ensure_table();

        global $wpdb;

        $sanitized = self::sanitize_row( $row );
        $elements  = json_decode( $sanitized['elements'], true );
        if ( ! is_array( $elements ) || empty( $elements ) ) {
            return false;
        }

        $public_id = ! empty( $sanitized['public_id'] ) ? $sanitized['public_id'] : wp_generate_uuid4();
        $now       = current_time( 'mysql', true );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $inserted = $wpdb->insert(
            self::get_table_name(),
            [
                'public_id'              => $public_id,
                'email_type'             => $sanitized['email_type'],
                'name'                   => $sanitized['name'],
                'description'            => $sanitized['description'],
                'elements'               => $sanitized['elements'],
                'email_settings'         => $sanitized['email_settings'],
                'global_header_settings' => $sanitized['global_header_settings'],
                'global_footer_settings' => $sanitized['global_footer_settings'],
                'position'               => $sanitized['position'],
                'created_by'             => $sanitized['created_by'],
                'created_at'             => $now,
                'updated_at'             => $now,
            ],
            [
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%d',
                '%s',
                '%s',
            ]
        );

        if ( false === $inserted ) {
            return false;
        }

        return $public_id;
    }

    /**
     * Insert multiple rows in a transaction.
     *
     * @param array[] $rows Rows to insert.
     *
     * @return string[] public_ids created.
     */
    public static function create_many( $rows ) {
        if ( empty( $rows ) ) {
            return [];
        }

        global $wpdb;

        $public_ids = [];
        $wpdb->query( 'START TRANSACTION' );

        try {
            foreach ( $rows as $row ) {
                $public_id = self::create( $row );
                if ( false === $public_id ) {
                    throw new \RuntimeException( 'Failed to insert library template row.' );
                }
                $public_ids[] = $public_id;
            }
            $wpdb->query( 'COMMIT' );
        } catch ( \Exception $e ) {
            $wpdb->query( 'ROLLBACK' );
            return [];
        }

        return $public_ids;
    }

    /**
     * List saved templates for email type (language-agnostic).
     *
     * @param string $email_type Email type slug.
     *
     * @return array[]
     */
    public static function list_by_email_type( $email_type ) {
        return array();
    }

    /**
     * Hard-delete a row by public_id.
     *
     * @param string $public_id UUID.
     *
     * @return bool
     */
    public static function delete_by_public_id( $public_id ) {
        self::ensure_table();

        global $wpdb;

        $public_id = sanitize_text_field( $public_id );
        if ( '' === $public_id ) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $deleted = $wpdb->delete(
            self::get_table_name(),
            [ 'public_id' => $public_id ],
            [ '%s' ]
        );

        return false !== $deleted && $deleted > 0;
    }

    /**
     * Map DB row to Template Library API summary shape.
     *
     * @param array $row Database row.
     *
     * @return array
     */
    public static function to_library_summary( $row ) {
        $elements       = json_decode( $row['elements'] ?? '[]', true );
        $email_settings = json_decode( $row['email_settings'] ?? '{}', true );

        if ( ! is_array( $elements ) ) {
            $elements = [];
        }
        if ( ! is_array( $email_settings ) ) {
            $email_settings = [];
        }

        return [
            'id'             => self::SAVED_ID_PREFIX . ( $row['public_id'] ?? '' ),
            'email_type'     => $row['email_type'] ?? '',
            'name'           => $row['name'] ?? '',
            'description'    => $row['description'] ?? '',
            'elements'       => $elements,
            'email_settings' => $email_settings,
            'position'       => isset( $row['position'] ) ? (int) $row['position'] : 100,
            'available'      => true,
            'access'         => 'free',
            'source'         => 'saved',
        ];
    }

    /**
     * Build sanitized row from REST save payload.
     *
     * @param array $payload Request payload fields.
     *
     * @return array
     */
    public static function build_row_from_save_payload( $payload ) {
        $elements = isset( $payload['elements'] ) && is_array( $payload['elements'] )
            ? TemplateHelpers::sanitize_elements_recursive( $payload['elements'] )
            : [];

        $email_settings = isset( $payload['email_settings'] ) && is_array( $payload['email_settings'] )
            ? $payload['email_settings']
            : [];

        $global_header = isset( $payload['global_header_settings'] ) && is_array( $payload['global_header_settings'] )
            ? $payload['global_header_settings']
            : null;

        $global_footer = isset( $payload['global_footer_settings'] ) && is_array( $payload['global_footer_settings'] )
            ? $payload['global_footer_settings']
            : null;

        return [
            'email_type'             => sanitize_text_field( $payload['email_type'] ?? '' ),
            'name'                   => sanitize_text_field( $payload['name'] ?? '' ),
            'description'            => '',
            'elements'               => wp_json_encode( $elements ),
            'email_settings'         => wp_json_encode( $email_settings ),
            'global_header_settings' => null !== $global_header ? wp_json_encode( $global_header ) : null,
            'global_footer_settings' => null !== $global_footer ? wp_json_encode( $global_footer ) : null,
            'position'               => 100,
            'created_by'             => get_current_user_id(),
        ];
    }

    /**
     * @param array $row Raw row before insert.
     *
     * @return array
     */
    private static function sanitize_row( $row ) {
        $elements = isset( $row['elements'] ) ? $row['elements'] : '[]';
        if ( is_array( $elements ) ) {
            $elements = wp_json_encode( TemplateHelpers::sanitize_elements_recursive( $elements ) );
        }

        return [
            'public_id'              => isset( $row['public_id'] ) ? sanitize_text_field( $row['public_id'] ) : '',
            'email_type'             => sanitize_text_field( $row['email_type'] ?? '' ),
            'name'                   => sanitize_text_field( $row['name'] ?? '' ),
            'description'            => sanitize_text_field( $row['description'] ?? '' ),
            'elements'               => $elements,
            'email_settings'         => is_string( $row['email_settings'] ?? '' ) ? $row['email_settings'] : wp_json_encode( [] ),
            'global_header_settings' => $row['global_header_settings'] ?? null,
            'global_footer_settings' => $row['global_footer_settings'] ?? null,
            'position'               => isset( $row['position'] ) ? (int) $row['position'] : 100,
            'created_by'             => isset( $row['created_by'] ) ? (int) $row['created_by'] : get_current_user_id(),
        ];
    }

    /**
     * @return void
     */
    private static function ensure_table() {
        LibraryTemplateSchema::maybe_create_table();
    }
}