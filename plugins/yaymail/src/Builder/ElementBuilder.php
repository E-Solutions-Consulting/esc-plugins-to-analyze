<?php
namespace YayMail\Builder;

/**
 * Builder class for constructing email elements
 */
class ElementBuilder {
    /**
     * Array of elements to build
     *
     * @var array
     */
    protected $elements = [];

    /**
     * Constructor
     */
    public function __construct() {}

    public function add_element( $type, $attributes ) {
        $this->elements[] = [
            'type'       => $type,
            'attributes' => $attributes,
        ];

        return $this;
    }

    public function build() {
        $built_elements = [];

        foreach ( $this->elements as $element ) {
            $element_type = $element['type'];
            $attributes   = $element['attributes'];

            if ( isset( $element['integration'] ) && '3rd' === $element['integration'] ) {
                $class = 'YayMail\Integrations\\' . $element_type;
            } elseif ( ! empty( $element['addon_namespace'] ) ) {
                $class = $element['addon_namespace'] . '\\Elements\\' . $element_type;
            } else {
                $class = 'YayMail\Elements\\' . $element_type;
            }

            if ( ! class_exists( $class ) ) {
                continue;
            }

            if ( 'ColumnLayout' === $element_type ) {
                $amount_of_columns = $attributes['amount_of_columns'] ?? 1;
                unset( $attributes['amount_of_columns'] );
                $element_data = $class::get_data( $amount_of_columns, $attributes );
            } elseif ( 'Column' === $element_type ) {
                $width = $attributes['column_width'] ?? 5;
                unset( $attributes['column_width'] );
                $element_data = $class::get_data( $width, $attributes );
            } else {
                $element_data = $class::get_data( $attributes );
            }

            if ( isset( $attributes['children'] ) && is_array( $attributes['children'] ) ) {
                $element_data['children'] = array_merge( ...array_values( $attributes['children'] ) );
            }

            $built_elements[] = $element_data;
        }//end foreach

        return $built_elements;
    }
}
