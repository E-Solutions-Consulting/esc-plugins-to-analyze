<?php

namespace YayMail\Models;

use YayMail\Utils\SingletonTrait;
use YayMail\YayMailTemplate;

/**
 * Revision Model
 *
 * @method static RevisionModel get_instance()
 */
class RevisionModel {

    use SingletonTrait;

    private $meta_keys = YayMailTemplate::META_KEYS;

    /**
     * Save revision of updated template
     *
     * @param int   $post_id ID of the updated template
     * @param array $updated_data An array containing the updated data fields and values
     *
     * @return void
     */
    public function save( $post_id, $updated_data ) {
        $all_revisions   = array_values(
            wp_get_post_revisions(
                $post_id,
                [
                    'order'       => 'DESC',
                    'numberposts' => 1,
                ]
            )
        );
        $latest_revision = $all_revisions[0] ?? null;

        if ( empty( $latest_revision ) ) {
            return;
        }

        $revision_post_id = $latest_revision->ID;
        if ( ! $revision_post_id ) {
            return null;
        }

        /**
         * Update the post_modified as it was copied from parent
         */
        $current_datetime = current_time( 'mysql' );
        wp_update_post(
            [
                'ID'                => $revision_post_id,
                'post_modified'     => $current_datetime,
                'post_modified_gmt' => get_gmt_from_date( $current_datetime ),
            ]
        );

        $updated_data['modified_by'] = wp_get_current_user()->data->display_name;
        foreach ( $updated_data as $key => $value ) {
            if ( isset( $this->meta_keys[ $key ] ) ) {
                update_metadata( 'post', $revision_post_id, $this->meta_keys[ $key ], $value );
            }
        }

        return [
            'revision_id'       => $revision_post_id,
            'modified_by'       => get_post_meta( $revision_post_id, $this->meta_keys['modified_by'], true ),
            'modified_at'       => get_post( $revision_post_id )->post_modified,
            'template_elements' => isset( $updated_data['elements'] ) ? $updated_data['elements'] : [],
            'background_color'  => isset( $updated_data['background_color'] ) ? $updated_data['background_color'] : '',
            'text_link_color'   => isset( $updated_data['text_link_color'] ) ? $updated_data['text_link_color'] : '',
        ];
    }

    public function get_by_template( $template_name ) {
        $template_data = TemplateModel::find_by_name( $template_name );
        if ( empty( $template_data['id'] ) ) {
            return [];
        }
        $revisions = wp_get_post_revisions( $template_data['id'] );
        $result    = [];

        foreach ( $revisions as $revision ) {
            $result[] = array_merge(
                [
                    'revision_id' => $revision->ID,
                    'modified_by' => get_post_meta( $revision->ID, $this->meta_keys['modified_by'], true ),
                    'modified_at' => $revision->post_modified,
                ],
                $this->get_by_id( $revision->ID )
            );
        }

        return $result;
    }
    public function get_by_id( $revision_id ) {
        $result                      = [];
        $result['template_elements'] = get_post_meta( $revision_id, $this->meta_keys['elements'], true );
        $result['background_color']  = get_post_meta( $revision_id, $this->meta_keys['background_color'], true );
        $result['text_link_color']   = get_post_meta( $revision_id, $this->meta_keys['text_link_color'], true );
        return $result;
    }

    public function delete_by_template( $template_name ) {
        $template_data = TemplateModel::find_by_name( $template_name );
        if ( empty( $template_data['id'] ) ) {
            return [
                'success' => false,
            ];
        }
        $revisions = wp_get_post_revisions( $template_data['id'] );

        foreach ( $revisions as $revision ) {
            wp_delete_post_revision( $revision );
        }
    }
}
