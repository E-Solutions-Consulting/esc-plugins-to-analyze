<?php
/**
 * Pure helpers for Order Progress email templates (mirrors order-progress-variant-logic.ts).
 *
 * @package YayMail
 */

namespace YayMail\Utils;

/**
 * Order Progress variant helpers.
 */
class OrderProgressVariantHelpers {

    /**
     * Step-marker column presets (widths sum 100). Must stay in sync with order-progress-variant-logic.ts (STEP_MARKER_PRESETS).
     *
     * @return array Presets keyed by step count 1–5; each has 'widths' and 'aligns' lists.
     */
    public static function get_step_marker_presets() {
        return [
            1 => [
                'widths' => [ 100 ],
                'aligns' => [ 'center' ],
            ],
            2 => [
                'widths' => [ 50, 50 ],
                'aligns' => [ 'left', 'right' ],
            ],
            3 => [
                'widths' => [ 33, 34, 33 ],
                'aligns' => [ 'left', 'center', 'right' ],
            ],
            4 => [
                'widths' => [ 12, 25, 25, 12 ],
                'aligns' => [ 'left', 'center', 'center', 'right' ],
            ],
            5 => [
                'widths' => [ 9, 20, 20, 20, 9 ],
                'aligns' => [ 'left', 'center', 'center', 'center', 'right' ],
            ],
        ];
    }

    /**
     * Connector segment colors for filled-bar inner table (left bar, icon, right bar).
     *
     * @param int    $index Step index (0-based).
     * @param int    $active_index Active step index (0-based).
     * @param int    $step_count Total steps.
     * @param string $active_color Connector active color.
     * @param string $inactive_color Connector inactive color.
     * @return array{left: string, right: string}
     */
    public static function get_connector_segment_colors( $index, $active_index, $step_count, $active_color, $inactive_color ) {
        $index        = (int) $index;
        $active_index = (int) $active_index;
        $step_count   = (int) $step_count;

        $left = ( 0 === $index )
            ? 'transparent'
            : ( $index <= $active_index ? $active_color : $inactive_color );

        $right = ( $index === $step_count - 1 )
            ? 'transparent'
            : ( $index < $active_index ? $active_color : $inactive_color );

        return [
            'left'  => $left,
            'right' => $right,
        ];
    }

    /**
     * Read step image URL from step array (new key with legacy fallback).
     *
     * @param array<string, mixed> $step Step data.
     * @return string
     */
    public static function get_step_image_url( $step ) {
        if ( ! is_array( $step ) ) {
            return '';
        }
        $image_url = isset( $step['image_url'] ) ? trim( (string) $step['image_url'] ) : '';
        if ( '' !== $image_url ) {
            return $image_url;
        }
        $active = isset( $step['image_active_url'] ) ? trim( (string) $step['image_active_url'] ) : '';
        if ( '' !== $active ) {
            return $active;
        }
        return isset( $step['image_inactive_url'] ) ? trim( (string) $step['image_inactive_url'] ) : '';
    }

    /**
     * Element-level active/inactive label color (per-step legacy overrides on read).
     *
     * @param array<string, mixed> $step Step data.
     * @param bool                 $is_step_active Whether the step is active or completed.
     * @param string               $label_active_color Element label color (active).
     * @param string               $label_inactive_color Element label color (inactive).
     * @return string
     */
    public static function get_step_label_color(
        $step,
        $is_step_active,
        $label_active_color = '#111827',
        $label_inactive_color = '#9CA3AF'
    ) {
        if ( is_array( $step ) ) {
            $label_color = isset( $step['label_color'] ) ? trim( (string) $step['label_color'] ) : '';
            if ( '' !== $label_color ) {
                return $label_color;
            }
            if ( $is_step_active ) {
                $legacy_active = isset( $step['label_active_color'] ) ? trim( (string) $step['label_active_color'] ) : '';
                if ( '' !== $legacy_active ) {
                    return $legacy_active;
                }
            } else {
                $legacy_inactive = isset( $step['label_inactive_color'] ) ? trim( (string) $step['label_inactive_color'] ) : '';
                if ( '' !== $legacy_inactive ) {
                    return $legacy_inactive;
                }
            }
        }

        $active_color   = trim( (string) $label_active_color );
        $inactive_color = trim( (string) $label_inactive_color );

        if ( $is_step_active ) {
            return '' !== $active_color ? $active_color : '#111827';
        }

        return '' !== $inactive_color ? $inactive_color : '#9CA3AF';
    }

    /**
     * Filled-bar icon border from step (new keys with legacy fallback).
     *
     * @param array<string, mixed> $step Step data.
     * @return array{color: string, style: string, width_px: int}
     */
    public static function get_step_icon_border( $step ) {
        $fallback_color = '#c9a8ff';
        if ( ! is_array( $step ) ) {
            return [
                'color'    => $fallback_color,
                'style'    => 'solid',
                'width_px' => 2,
            ];
        }
        $color = isset( $step['icon_border_color'] ) ? trim( (string) $step['icon_border_color'] ) : '';
        if ( '' === $color && isset( $step['filled_bar_icon_border_color_active'] ) ) {
            $color = trim( (string) $step['filled_bar_icon_border_color_active'] );
        }
        if ( '' === $color && isset( $step['filled_bar_icon_border_color_inactive'] ) ) {
            $color = trim( (string) $step['filled_bar_icon_border_color_inactive'] );
        }
        if ( '' === $color ) {
            $color = $fallback_color;
        }
        $style = isset( $step['icon_border_style'] ) ? strtolower( trim( (string) $step['icon_border_style'] ) ) : 'solid';
        if ( ! in_array( $style, [ 'solid', 'dashed', 'dotted' ], true ) ) {
            $style = 'solid';
        }
        $width_px = 2;
        if ( isset( $step['icon_border_width'] ) ) {
            $width_px = max( 0, min( 10, (int) round( (float) $step['icon_border_width'] ) ) );
        }
        return [
            'color'    => $color,
            'style'    => $style,
            'width_px' => $width_px,
        ];
    }

    /**
     * @deprecated Use get_step_image_url() on step array.
     */
    public static function resolve_step_image_url( $is_step_active, $active_url, $inactive_url ) {
        $active_url   = (string) $active_url;
        $inactive_url = (string) $inactive_url;
        $chosen       = $is_step_active ? $active_url : $inactive_url;
        if ( '' !== $chosen ) {
            return $chosen;
        }
        return '' !== $active_url ? $active_url : $inactive_url;
    }

    /**
     * Filled-bar label text color (per-step label_color with legacy fallbacks).
     *
     * @param array<string, mixed> $step Step data.
     * @param string               $legacy_label_color Legacy single label color on step.
     * @param string               $global_label_active_color Element default (legacy).
     * @param string               $global_label_inactive_color Element default (legacy).
     * @return string
     */
    public static function resolve_filled_bar_label_color(
        $step,
        $is_step_active,
        $label_active_color = '#111827',
        $label_inactive_color = '#9CA3AF'
    ) {
        return self::get_step_label_color(
            $step,
            $is_step_active,
            $label_active_color,
            $label_inactive_color
        );
    }

    /**
     * Filled-bar icon wrapper border color (single per-step color).
     *
     * @param array<string, mixed> $step Step data.
     * @return string
     */
    public static function resolve_filled_bar_icon_border_color( $step ) {
        $border = self::get_step_icon_border( $step );
        return $border['color'];
    }

    /**
     * Step-marker current-step accent: per-step image_bg_color when set, else connector active.
     *
     * @param array<string, mixed> $step Step data.
     * @param string               $connector_active_color Element connector active color.
     * @return string
     */
    public static function resolve_step_marker_accent_color( $step, $connector_active_color ) {
        if ( is_array( $step ) ) {
            $bg = trim( (string) ( $step['image_bg_color'] ?? '' ) );
            if ( '' !== $bg ) {
                return $bg;
            }
        }
        return (string) $connector_active_color;
    }

    /**
     * Blend a hex color toward white (step-marker ring border).
     *
     * @param string $hex    Color such as #2563eb.
     * @param float  $amount Blend amount 0–1.
     * @return string
     */
    public static function blend_hex_with_white( $hex, $amount = 0.55 ) {
        $raw = ltrim( (string) $hex, '#' );
        if ( 6 !== strlen( $raw ) || ! ctype_xdigit( $raw ) ) {
            return '#C4B0E8';
        }
        $r0  = hexdec( substr( $raw, 0, 2 ) );
        $g0  = hexdec( substr( $raw, 2, 2 ) );
        $b0  = hexdec( substr( $raw, 4, 2 ) );
        $mix = static function ( $c ) use ( $amount ) {
            return (int) round( $c + ( 255 - $c ) * $amount );
        };
        return sprintf( '#%02x%02x%02x', $mix( $r0 ), $mix( $g0 ), $mix( $b0 ) );
    }
}
