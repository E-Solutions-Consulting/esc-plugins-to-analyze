<?php
/**
 * BH External Database
 *
 * Standalone mysqli connection to the BH external tracking database.
 * Completely independent from $wpdb — no shared state, no swap risks.
 *
 * Design principles:
 *  - All queries use prepared statements (no string concatenation)
 *  - Single connection per request (singleton)
 *  - Fails silently with logging — never breaks the main site
 *  - No public access to the raw mysqli connection
 *
 * Required constants in wp-config.php:
 *   define( 'BH_EXT_DB_NAME',     'bh_external' );
 *   define( 'BH_EXT_DB_USER',     'db_user' );
 *   define( 'BH_EXT_DB_PASSWORD', 'db_pass' );
 *   define( 'BH_EXT_DB_HOST',     'localhost' );
 *
 * Usage:
 *   BH_ExtDB::insert( 'bh_events', [ 'order_id' => 123, 'type' => 'pending' ] );
 *   BH_ExtDB::get_var( "SELECT triggered_at FROM bh_events WHERE order_id = ?", [ 123 ] );
 *   BH_ExtDB::query( "SELECT * FROM bh_events WHERE type = ?", [ 'pending' ] );
 *
 * @package    BH_Features
 * @subpackage Common
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BH_ExtDB {

    /**
     * The mysqli connection instance.
     * Only accessible internally — never exposed publicly.
     *
     * @var mysqli|null
     */
    private static ?mysqli $conn = null;

    /**
     * Whether a connection attempt has already been made this request.
     * Prevents hammering the DB server on repeated failures.
     *
     * @var bool
     */
    private static bool $attempted = false;

    // =========================================================================
    // CONNECTION
    // =========================================================================

    /**
     * Get the mysqli connection, creating it on first call.
     * Returns null if constants are missing or connection fails.
     *
     * Note: Do NOT call select_db() on the cached connection — it internally
     * executes a query that leaves a pending result and causes "Commands out of
     * sync" on the next prepared statement. The DB is selected at connect time
     * via the mysqli constructor and remains selected for the lifetime of the
     * connection.
     *
     * @return mysqli|null
     */
    private static function connection(): ?mysqli {

        if ( self::$conn !== null ) {
            return self::$conn;
        }

        if ( self::$attempted ) {
            return null;
        }

        self::$attempted = true;

        if (
            ! defined( 'BH_EXT_DB_NAME' ) ||
            ! defined( 'BH_EXT_DB_USER' ) ||
            ! defined( 'BH_EXT_DB_PASSWORD' ) ||
            ! defined( 'BH_EXT_DB_HOST' )
        ) {
            self::log( 'Missing constants. Define BH_EXT_DB_* in wp-config.php.' );
            return null;
        }

        $conn = @new mysqli(
            BH_EXT_DB_HOST,
            BH_EXT_DB_USER,
            BH_EXT_DB_PASSWORD,
            BH_EXT_DB_NAME
        );

        if ( $conn->connect_errno ) {
            self::log( 'Connection failed: ' . $conn->connect_error );
            return null;
        }

        // Force utf8mb4 — prevents charset mismatches and silent data corruption.
        if ( ! $conn->set_charset( 'utf8mb4' ) ) {
            self::log( 'Could not set charset utf8mb4: ' . $conn->error );
        }

        self::$conn = $conn;

        return self::$conn;
    }

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    /**
     * Check if the external DB is available.
     *
     * @return bool
     */
    public static function is_available(): bool {
        return self::connection() !== null;
    }

    /**
     * Run a SELECT query and return all rows as associative arrays.
     *
     * @param string $sql     Query with ? placeholders.
     * @param array  $params  Values to bind. Each entry is a scalar.
     * @return array[]  Empty array on failure or no results.
     */
    public static function query( string $sql, array $params = [] ): array {
        $stmt = self::prepare( $sql, $params );
        if ( $stmt === null ) {
            return [];
        }

        if ( ! $stmt->execute() ) {
            self::log( 'execute() failed: ' . $stmt->error . ' | SQL: ' . $sql );
            $stmt->close();
            return [];
        }

        $result = $stmt->get_result();
        if ( $result === false ) {
            self::log( 'get_result failed: ' . $stmt->error );
            $stmt->close();
            return [];
        }

        $rows = $result->fetch_all( MYSQLI_ASSOC );
        $result->free();
        $stmt->close();

        return $rows;
    }

    /**
     * Run a SELECT query and return a single scalar value.
     * Returns null if no row matches or on failure.
     *
     * @param string $sql
     * @param array  $params
     * @return mixed|null
     */
    public static function get_var( string $sql, array $params = [] ): mixed {
        $stmt = self::prepare( $sql, $params );
        if ( $stmt === null ) {
            return null;
        }

        if ( ! $stmt->execute() ) {
            self::log( 'execute() failed: ' . $stmt->error . ' | SQL: ' . $sql );
            $stmt->close();
            return null;
        }

        $result = $stmt->get_result();
        if ( $result === false ) {
            self::log( 'get_result failed: ' . $stmt->error );
            $stmt->close();
            return null;
        }

        $row = $result->fetch_row();
        $result->free();
        $stmt->close();

        return $row[0] ?? null;
    }

    /**
     * Run a SELECT query and return a single row as associative array.
     * Returns null if no row matches or on failure.
     *
     * @param string $sql
     * @param array  $params
     * @return array|null
     */
    public static function get_row( string $sql, array $params = [] ): ?array {
        $stmt = self::prepare( $sql, $params );
        if ( $stmt === null ) {
            return null;
        }

        if ( ! $stmt->execute() ) {
            self::log( 'execute() failed: ' . $stmt->error . ' | SQL: ' . $sql );
            $stmt->close();
            return null;
        }

        $result = $stmt->get_result();
        if ( $result === false ) {
            self::log( 'get_result failed: ' . $stmt->error );
            $stmt->close();
            return null;
        }

        $row = $result->fetch_assoc();
        $result->free();
        $stmt->close();

        return $row ?? null;
    }

    /**
     * INSERT a row into a table.
     * Automatically infers types from PHP variable types.
     *
     * @param string $table  Table name (hardcoded strings only).
     * @param array  $data   Associative array of column => value.
     * @return int|false  insert_id on success, false on failure.
     */
    public static function insert( string $table, array $data ): int|false {
        if ( empty( $data ) ) {
            return false;
        }

        $conn = self::connection();
        if ( $conn === null ) {
            return false;
        }

        $columns      = array_keys( $data );
        $values       = array_values( $data );
        $placeholders = implode( ', ', array_fill( 0, count( $values ), '?' ) );
        $column_list  = implode( ', ', array_map( [ self::class, 'escape_identifier' ], $columns ) );

        $sql  = "INSERT INTO " . self::escape_identifier( $table );
        $sql .= " ({$column_list}) VALUES ({$placeholders})";

        $stmt = self::prepare( $sql, $values );
        if ( $stmt === null ) {
            return false;
        }

        if ( ! $stmt->execute() ) {
            self::log( 'INSERT failed on ' . $table . ': ' . $stmt->error );
            $stmt->close();
            return false;
        }

        $insert_id = $conn->insert_id;
        $stmt->close();

        return $insert_id;
    }

    /**
     * UPDATE rows in a table.
     *
     * @param string $table  Table name.
     * @param array  $data   Columns to update: column => value.
     * @param array  $where  WHERE conditions: column => value (AND logic).
     * @return int|false  Number of affected rows, or false on failure.
     */
    public static function update( string $table, array $data, array $where ): int|false {
        if ( empty( $data ) || empty( $where ) ) {
            return false;
        }

        $conn = self::connection();
        if ( $conn === null ) {
            return false;
        }

        $set_parts   = [];
        $where_parts = [];
        $params      = [];

        foreach ( $data as $col => $val ) {
            $set_parts[] = self::escape_identifier( $col ) . ' = ?';
            $params[]    = $val;
        }

        foreach ( $where as $col => $val ) {
            $where_parts[] = self::escape_identifier( $col ) . ' = ?';
            $params[]      = $val;
        }

        $sql  = "UPDATE " . self::escape_identifier( $table );
        $sql .= " SET " . implode( ', ', $set_parts );
        $sql .= " WHERE " . implode( ' AND ', $where_parts );

        $stmt = self::prepare( $sql, $params );
        if ( $stmt === null ) {
            return false;
        }

        if ( ! $stmt->execute() ) {
            self::log( 'UPDATE failed on ' . $table . ': ' . $stmt->error );
            $stmt->close();
            return false;
        }

        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected;
    }

    /**
     * INSERT a row, or UPDATE it if the PRIMARY KEY / UNIQUE key already exists.
     *
     * @param string $table
     * @param array  $data         Columns to insert.
     * @param array  $update_data  Columns to update on duplicate. If empty, updates all $data columns.
     * @return int|false  insert_id or affected rows, false on failure.
     */
    public static function upsert( string $table, array $data, array $update_data = [] ): int|false {
        if ( empty( $data ) ) {
            return false;
        }

        $conn = self::connection();
        if ( $conn === null ) {
            return false;
        }

        if ( empty( $update_data ) ) {
            $update_data = $data;
        }

        $columns      = array_keys( $data );
        $insert_vals  = array_values( $data );
        $placeholders = implode( ', ', array_fill( 0, count( $insert_vals ), '?' ) );
        $column_list  = implode( ', ', array_map( [ self::class, 'escape_identifier' ], $columns ) );

        $update_parts = [];
        $update_vals  = [];
        foreach ( $update_data as $col => $val ) {
            $update_parts[] = self::escape_identifier( $col ) . ' = ?';
            $update_vals[]  = $val;
        }

        $sql  = "INSERT INTO " . self::escape_identifier( $table );
        $sql .= " ({$column_list}) VALUES ({$placeholders})";
        $sql .= " ON DUPLICATE KEY UPDATE " . implode( ', ', $update_parts );

        $params = array_merge( $insert_vals, $update_vals );

        $stmt = self::prepare( $sql, $params );
        if ( $stmt === null ) {
            return false;
        }

        if ( ! $stmt->execute() ) {
            self::log( 'UPSERT failed on ' . $table . ': ' . $stmt->error );
            $stmt->close();
            return false;
        }

        $result = $conn->insert_id ?: $stmt->affected_rows;
        $stmt->close();

        return $result;
    }

    /**
     * Execute a raw SQL statement (CREATE TABLE, ALTER TABLE, etc.)
     * Not for SELECT/INSERT/UPDATE — use the specific methods for those.
     *
     * @param string $sql  Raw SQL. No params — only for DDL statements.
     * @return bool
     */
    public static function execute( string $sql ): bool {
        $conn = self::connection();
        if ( $conn === null ) {
            return false;
        }

        if ( ! $conn->query( $sql ) ) {
            self::log( 'execute() failed: ' . $conn->error . ' | SQL: ' . $sql );
            return false;
        }

        return true;
    }

    // =========================================================================
    // INTERNAL HELPERS
    // =========================================================================

    /**
     * Build and bind a prepared statement.
     * Automatically infers bind types from PHP variable types.
     *
     * @param string $sql
     * @param array  $params
     * @return mysqli_stmt|null
     */
    private static function prepare( string $sql, array $params ): ?mysqli_stmt {
        $conn = self::connection();
        if ( $conn === null ) {
            return null;
        }

        $stmt = $conn->prepare( $sql );
        if ( $stmt === false ) {
            self::log( 'prepare() failed: ' . $conn->error . ' | SQL: ' . $sql );
            return null;
        }

        if ( ! empty( $params ) ) {
            $types = self::infer_types( $params );

            if ( ! $stmt->bind_param( $types, ...$params ) ) {
                self::log( 'bind_param failed: ' . $stmt->error );
                $stmt->close();
                return null;
            }
        }

        return $stmt;
    }

    /**
     * Infer mysqli bind type string from array of values.
     *
     * @param array $params
     * @return string  e.g. 'issd' for (int, string, string, float)
     */
    private static function infer_types( array $params ): string {
        $types = '';
        foreach ( $params as $param ) {
            if ( is_int( $param ) || is_bool( $param ) ) {
                $types .= 'i';
            } elseif ( is_float( $param ) ) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        return $types;
    }

    /**
     * Escape a column or table identifier with backticks.
     * Only allows alphanumeric characters and underscores.
     *
     * @param string $identifier
     * @return string
     * @throws InvalidArgumentException if identifier contains invalid characters.
     */
    private static function escape_identifier( string $identifier ): string {
        if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $identifier ) ) {
            self::log( 'Invalid identifier rejected: ' . $identifier );
            throw new InvalidArgumentException(
                '[BH_ExtDB] Invalid identifier: "' . $identifier . '". Only alphanumeric and underscores allowed.'
            );
        }
        return '`' . $identifier . '`';
    }

    /**
     * Log an error message to WP error log.
     *
     * @param string $message
     */
    private static function log( string $message ): void {
        error_log( '[BH_ExtDB] ' . $message );
    }

    /**
     * Prevent instantiation and cloning — static class only.
     */
    private function __construct() {}
    private function __clone() {}
}