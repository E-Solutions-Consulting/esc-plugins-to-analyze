<?php
defined( 'ABSPATH' ) || exit;
use YayMail\Utils\TemplateHelpers;
use YayMail\Models\TemplateModel;
use YayMail\Elements\ElementsLoader;

/**
 * $args includes
 * $element
 * $render_data
 * $is_nested
 */
if ( empty( $args['element'] ) ) {
    return;
}

$element = $args['element'];
$data    = $element['data'];

$template_model = TemplateModel::get_instance();

$global_header_footer = $template_model->get_global_header_and_footer();

$global_header_elements = isset( $global_header_footer['global_header_elements'] ) ? $global_header_footer['global_header_elements'] : [];

if ( ! empty( $data['rich_text'] ) ) {
    $global_header_elements = array_map(
        function( $element ) use ( $data ) {
            if ( isset( $element['type'] ) && 'heading' === $element['type'] && isset( $element['data'] ) ) {
                $element['data']['rich_text'] = $data['rich_text'];
            }
            return $element;
        },
        $global_header_elements
    );
}

ob_start();
ElementsLoader::render_elements( $global_header_elements, $args );
$element_content = ob_get_clean();

$allowed_html = TemplateHelpers::wp_kses_allowed_html();
echo wp_kses( $element_content, $allowed_html );

// TemplateHelpers::wrap_element_content( $element_content, $element );
