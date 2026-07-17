<?php

function convert_element_type_to_class_name( $element_type ) {
    $element_class_name = str_replace( '_', ' ', $element_type );
    $element_class_name = ucwords( $element_class_name );
    $element_class_name = str_replace( ' ', '', $element_class_name );
    return $element_class_name;
}

function convert_json_elements_to_php( $json, $class_name, $section_type, $pattern_type, $pattern_name, $position = 10 ) {
    $elements = json_decode( $json, true );

    // Start building the PHP code
    $php = "<?php\nnamespace YayMail\\TemplatePatterns\\Patterns;\n\n";

    // Add imports
    $php .= "use YayMail\\Abstracts\\BasePattern;\n";

    // Collect all element types for imports (including nested elements)
    $element_types = collect_all_element_types( $elements );
    foreach ( $element_types as $element_type ) {
        $element_class_name = convert_element_type_to_class_name( $element_type );
        $php               .= "use YayMail\\Elements\\{$element_class_name};\n";
    }

    $php .= "use YayMail\\TemplatePatterns\\SectionTemplates\\{$section_type};\n";
    $php .= "use YayMail\\Utils\\SingletonTrait;\n\n";

    // Class definition
    $php .= "/**\n * {$class_name} Class\n */\n";
    $php .= "class {$class_name} extends BasePattern {\n";
    $php .= "    use SingletonTrait;\n\n";
    $php .= "    public const TYPE = '{$pattern_type}';\n\n";

    // Constructor
    $php .= "    public function __construct() {\n";
    $php .= "        \$this->id       = uniqid();\n";
    $php .= "        \$this->section  = {$section_type}::TYPE;\n";
    $php .= "        \$this->position = {$position};\n";
    $php .= "        \$this->name     = __('{$pattern_name}', 'yaymail');\n";
    $php .= "        \$this->elements = [\n";

    // Add each element
    foreach ( $elements as $element ) {
        $php .= process_element( $element, 3 );
    }

    // Close elements array and class
    $php .= "        ];\n";
    $php .= "    }\n";
    $php .= "}\n";

    return $php;
}

function collect_all_element_types( $elements ) {
    $types = [];

    foreach ( $elements as $element ) {
        // Add this element's type
        if ( ! in_array( $element['type'], $types ) ) {
            $types[] = $element['type'];
        }

        // Process children if they exist
        if ( isset( $element['children'] ) && is_array( $element['children'] ) ) {
            $child_types = collect_all_element_types( $element['children'] );
            foreach ( $child_types as $type ) {
                if ( ! in_array( $type, $types ) ) {
                    $types[] = $type;
                }
            }
        }
    }

    return $types;
}

function process_element( $element, $indent_level ) {
    $element_class_name = convert_element_type_to_class_name( $element['type'] );
    $indentation        = str_repeat( '    ', $indent_level );
    $php                = '';

    // Special case for ColumnLayout
    if ( $element['type'] === 'column_layout' ) {
        $columns_count = $element['data']['amount_of_columns'] ?? count( $element['children'] ?? [] );
        $php          .= "{$indentation}{$element_class_name}::get_object_data(\n";
        $php          .= "{$indentation}    {$columns_count},\n";
        $php          .= "{$indentation}    [\n";

        // Process normal attributes
        foreach ( $element['data'] as $key => $value ) {
            if ( $key !== 'amount_of_columns' && $key !== 'children' ) {
                $php .= convert_value_to_php( $key, $value, $indent_level + 1 );
            }
        }

        // Process children if they exist
        if ( isset( $element['children'] ) && ! empty( $element['children'] ) ) {
            $php .= "{$indentation}        'children' => [\n";
            foreach ( $element['children'] as $child ) {
                $php .= process_element( $child, $indent_level + 3 );
            }
            $php .= "{$indentation}        ],\n";
        }

        $php .= "{$indentation}    ]\n";
        $php .= "{$indentation}),\n";
    }//end if
    // Special case for Column
    elseif ( $element['type'] === 'column' ) {
        $width = $element['data']['width'] ?? 50;
        // Default width if not provided
        $php .= "{$indentation}{$element_class_name}::get_object_data(\n";
        $php .= "{$indentation}    {$width},\n";
        $php .= "{$indentation}    [\n";

        // Process children if they exist
        if ( isset( $element['children'] ) && ! empty( $element['children'] ) ) {
            $php .= "{$indentation}        'children' => [\n";
            foreach ( $element['children'] as $child ) {
                $php .= process_element( $child, $indent_level + 3 );
            }
            $php .= "{$indentation}        ],\n";
        }

        $php .= "{$indentation}    ]\n";
        $php .= "{$indentation}),\n";
    }
    // Regular elements
    else {
        $php .= "{$indentation}{$element_class_name}::get_object_data(\n";
        $php .= "{$indentation}    [\n";

        // Process data attributes
        if ( isset( $element['data'] ) ) {
            foreach ( $element['data'] as $key => $value ) {
                $php .= convert_value_to_php( $key, $value, $indent_level + 1 );
            }
        }

        $php .= "{$indentation}    ]\n";
        $php .= "{$indentation}),\n";
    }

    return $php;
}

function convert_value_to_php( $key, $value, $indent ) {
    $indentation = str_repeat( ' ', $indent * 4 );
    $php         = '';

    if ( is_array( $value ) ) {
        $php .= "{$indentation}'{$key}' => [\n";

        foreach ( $value as $sub_key => $sub_value ) {
            if ( is_array( $sub_value ) ) {
                $php .= convert_value_to_php( $sub_key, $sub_value, $indent + 1 );
            } else {
                if ( is_string( $sub_value ) ) {
                    // Escape apostrophes and backslashes for PHP single-quoted strings
                    $escaped_value   = str_replace( [ '\\', "'" ], [ '\\\\', "\\'" ], $sub_value );
                    $formatted_value = "'{$escaped_value}'";
                } else {
                    $formatted_value = $sub_value;
                }
                $php .= str_repeat( ' ', ( $indent + 1 ) * 4 ) . "'{$sub_key}' => {$formatted_value},\n";
            }
        }

        $php .= "{$indentation}],\n";
    } else {
        if ( is_string( $value ) ) {
            // Escape apostrophes and backslashes for PHP single-quoted strings
            $escaped_value   = str_replace( [ '\\', "'" ], [ '\\\\', "\\'" ], $value );
            $formatted_value = "'{$escaped_value}'";
        } else {
            $formatted_value = $value;
        }
        $php .= "{$indentation}'{$key}' => {$formatted_value},\n";
    }//end if

    return $php;
}

function create_pattern_file( $json_string, $section_type, $pattern_type, $pattern_name, $position = 10 ) {
    // Generate the PHP code
    $class_name = convert_element_type_to_class_name( $pattern_type );
    $php_code   = convert_json_elements_to_php( $json_string, $class_name, $section_type, $pattern_type, $pattern_name, $position );

    // Define the file path
    $patterns_dir = './Patterns';
    $file_path    = $patterns_dir . '/' . $class_name . '.php';

    // Write the PHP code to file
    if ( file_put_contents( $file_path, $php_code ) ) {
        return true;
    } else {
        return "Failed to write pattern file: {$file_path}";
    }
}

// Example usage (uncomment to use)
create_pattern_file(
    file_get_contents( './tool-json.json' ),
    'Banner',
    'banner_23',
    'Banner 23',
    $position = 20
);
