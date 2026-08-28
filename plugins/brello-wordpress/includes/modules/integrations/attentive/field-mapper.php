<?php
/**
 * Attentive Integration - Field Mapper
 * 
 * Maps Typeform fields to Attentive format.
 * 
 * @package BH_Features
 * @subpackage Integrations/Attentive
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BH_Attentive_Field_Mapper {

    /**
     * Map entire Typeform payload to Attentive format
     */
    public function map_typeform_to_attentive( $typeform_payload ) {
        $settings = BH_Attentive_Config::get_settings();
        
        $form_response = $typeform_payload['form_response'] ?? [];
        $answers = $form_response['answers'] ?? [];
        $hidden = $form_response['hidden'] ?? [];
        
        $mapped = array(
            'phone'            => '',
            'email'            => '',
            'form_id'          => $form_response['form_id'] ?? '',
            'submitted_at'     => $form_response['submitted_at'] ?? gmdate( 'c' ),
            'custom_attributes'=> array(),
            'event_properties' => array(),
        );
        
        // Extract phone and email
        $mapped['phone'] = $this->extract_and_format_phone( $answers, $settings['phone_field_id'] );
        $mapped['email'] = $this->extract_email( $answers, $settings['email_field_id'] );
        
        // Map hidden fields to custom attributes
        $mapped['custom_attributes'] = $this->map_hidden_fields( $hidden );
        
        // Map configured field mappings
        $custom_attrs = $this->map_custom_attributes( $answers, $settings['field_mappings'] );
        $mapped['custom_attributes'] = array_merge( $mapped['custom_attributes'], $custom_attrs );
        
        // Auto-extract common fields as custom attributes
        $auto_attrs = $this->extract_common_attributes( $answers );
        $mapped['custom_attributes'] = array_merge( $mapped['custom_attributes'], $auto_attrs );
        
        // Build event properties (simplified - only metadata)
        $mapped['event_properties'] = $this->build_event_properties( $answers, $form_response );
        
        return $mapped;
    }
    
    /**
     * Auto-extract common fields as custom attributes
     */
    private function extract_common_attributes( $answers ) {
        $attributes = array();
        
        foreach ( $answers as $answer ) {
            $field_ref = strtolower( $answer['field']['ref'] ?? '' );
            $value = $this->extract_answer_value( $answer );
            
            if ( empty( $value ) ) {
                continue;
            }
            
            // Map common field references to attribute names
            if ( strpos( $field_ref, 'fname' ) !== false || strpos( $field_ref, 'first' ) !== false ) {
                $attributes['first_name'] = $value;
            } elseif ( strpos( $field_ref, 'lname' ) !== false || strpos( $field_ref, 'last' ) !== false ) {
                $attributes['last_name'] = $value;
            } elseif ( strpos( $field_ref, 'product' ) !== false || strpos( $field_ref, 'interest' ) !== false ) {
                $attributes['product_interest'] = $value;
            } elseif ( strpos( $field_ref, 'hear' ) !== false || strpos( $field_ref, 'source' ) !== false ) {
                $attributes['source'] = $value;
            }
        }
        
        return $attributes;
    }

    /**
     * Extract phone number and format to E.164
     */
    public function extract_and_format_phone( $answers, $phone_field_id = '' ) {
        $phone = '';
        
        foreach ( $answers as $answer ) {
            $field_id = $answer['field']['id'] ?? '';
            $field_type = $answer['type'] ?? '';
            
            // Check by field ID or type
            if ( ( $phone_field_id && $field_id === $phone_field_id ) || $field_type === 'phone_number' ) {
                $phone = $answer['phone_number'] ?? $answer['text'] ?? '';
                break;
            }
        }
        
        return $this->format_phone_e164( $phone );
    }

    /**
     * Format phone number to E.164
     */
    public function format_phone_e164( $phone ) {
        if ( empty( $phone ) ) {
            return '';
        }
        
        // Remove all non-numeric characters except +
        $phone = preg_replace( '/[^0-9+]/', '', $phone );
        
        // If already starts with +, assume E.164
        if ( strpos( $phone, '+' ) === 0 ) {
            return $phone;
        }
        
        // If 10 digits, assume US and add +1
        if ( strlen( $phone ) === 10 ) {
            return '+1' . $phone;
        }
        
        // If 11 digits starting with 1, add +
        if ( strlen( $phone ) === 11 && strpos( $phone, '1' ) === 0 ) {
            return '+' . $phone;
        }
        
        // Default: add + prefix
        return '+' . $phone;
    }

    /**
     * Extract email from answers
     */
    public function extract_email( $answers, $email_field_id = '' ) {
        foreach ( $answers as $answer ) {
            $field_id = $answer['field']['id'] ?? '';
            $field_type = $answer['type'] ?? '';
            
            if ( ( $email_field_id && $field_id === $email_field_id ) || $field_type === 'email' ) {
                return sanitize_email( $answer['email'] ?? '' );
            }
        }
        
        return '';
    }

    /**
     * Map hidden fields to custom attributes
     */
    public function map_hidden_fields( $hidden ) {
        $attributes = array();
        
        if ( ! is_array( $hidden ) ) {
            return $attributes;
        }
        
        foreach ( $hidden as $key => $value ) {
            // Sanitize key and value
            $attr_name = sanitize_key( $key );
            $attr_value = sanitize_text_field( $value );
            
            // Only add non-empty values
            if ( ! empty( $attr_value ) ) {
                $attributes[ $attr_name ] = $attr_value;
            }
        }
        
        return $attributes;
    }

    /**
     * Map custom attributes based on configuration
     */
    public function map_custom_attributes( $answers, $field_mappings ) {
        $attributes = array();
        
        if ( ! is_array( $field_mappings ) ) {
            return $attributes;
        }
        
        foreach ( $field_mappings as $mapping ) {
            $typeform_field = $mapping['typeform_field'] ?? '';
            $attentive_attr = $mapping['attentive_attribute'] ?? '';
            
            if ( empty( $typeform_field ) || empty( $attentive_attr ) ) {
                continue;
            }
            
            // Find the answer for this field
            foreach ( $answers as $answer ) {
                $field_id = $answer['field']['id'] ?? '';
                
                if ( $field_id === $typeform_field ) {
                    $value = $this->extract_answer_value( $answer );
                    if ( ! empty( $value ) ) {
                        $attributes[ $attentive_attr ] = $value;
                    }
                    break;
                }
            }
        }
        
        return $attributes;
    }

    /**
     * Extract value from Typeform answer
     */
    private function extract_answer_value( $answer ) {
        $type = $answer['type'] ?? '';
        
        switch ( $type ) {
            case 'text':
            case 'short_text':
            case 'long_text':
                return sanitize_text_field( $answer['text'] ?? '' );
            
            case 'email':
                return sanitize_email( $answer['email'] ?? '' );
            
            case 'phone_number':
                return $this->format_phone_e164( $answer['phone_number'] ?? '' );
            
            case 'number':
                return intval( $answer['number'] ?? 0 );
            
            case 'boolean':
                return $answer['boolean'] ? 'yes' : 'no';
            
            case 'choice':
                return sanitize_text_field( $answer['choice']['label'] ?? '' );
            
            case 'choices':
                $labels = array_map( function( $choice ) {
                    return sanitize_text_field( $choice['label'] ?? '' );
                }, $answer['choices']['labels'] ?? [] );
                return implode( ', ', $labels );
            
            case 'date':
                return sanitize_text_field( $answer['date'] ?? '' );
            
            case 'rating':
            case 'opinion_scale':
                return intval( $answer['number'] ?? 0 );
            
            default:
                return sanitize_text_field( $answer['text'] ?? '' );
        }
    }

    /**
     * Build event properties from form response
     */
    public function build_event_properties( $answers, $form_response ) {
        // Only send essential properties to avoid API errors
        $properties = array(
            'form_id'      => $form_response['form_id'] ?? '',
            'submitted_at' => $form_response['submitted_at'] ?? '',
            'response_id'  => $form_response['token'] ?? '',
        );
        
        // Don't add all answers - causes "Invalid request content" error
        // Attentive has strict validation on event properties
        
        return $properties;
    }

    /**
     * Validate mapped data
     */
    public function validate_mapped_data( $data ) {
        $errors = array();
        
        // Phone is required
        if ( empty( $data['phone'] ) ) {
            $errors[] = 'Phone number is required';
        }
        
        // Validate phone format (E.164)
        if ( ! empty( $data['phone'] ) && ! preg_match( '/^\+[1-9]\d{1,14}$/', $data['phone'] ) ) {
            $errors[] = 'Invalid phone number format. Must be E.164 format (e.g., +15551234567)';
        }
        
        // Validate email if provided
        if ( ! empty( $data['email'] ) && ! is_email( $data['email'] ) ) {
            $errors[] = 'Invalid email address';
        }
        
        return empty( $errors ) ? true : $errors;
    }
}
