<?php

namespace Objectiv\Plugins\Checkout;

use Objectiv\Plugins\Checkout\Managers\SettingsManager;

class FormFieldAugmenter extends SingletonAbstract {
	protected $checkbox_like_field_types = array( 'checkbox', 'radio' );
	protected $filters_added             = false;

	public function add_hooks() {
		if ( $this->filters_added ) {
			return;
		}

		$this->filters_added = true;

		add_filter( 'cfw_pre_output_fieldset_field_args', array( $this, 'calculate_columns' ), 100000 - 1000, 1 );
		add_filter( 'woocommerce_form_field_args', array( $this, 'calculate_columns' ), 100000, 1 );
		add_filter( 'woocommerce_form_field_args', array( $this, 'cfw_form_field_args' ), 100000, 2 );
		add_filter( 'woocommerce_form_field', array( $this, 'remove_extraneous_field_classes' ), 100000, 1 );
		add_filter( 'woocommerce_form_field', array( $this, 'add_select_container_class' ), 200000, 1 );
		add_filter( 'woocommerce_form_field', array( $this, 'cleanup_space_between_checkbox_input_and_text' ), 200000, 3 );
		add_filter( 'woocommerce_form_field', array( $this, 'add_before_html' ), 200000, 3 );
		add_filter( 'woocommerce_form_field', array( $this, 'add_after_html' ), 200000, 3 );
		add_filter( 'woocommerce_form_field_password', array( $this, 'password_field_toggle' ), 200000, 4 );
	}

	public function remove_hooks() {
		if ( ! $this->filters_added ) {
			return;
		}

		$this->filters_added = false;

		remove_filter( 'cfw_pre_output_fieldset_field_args', array( $this, 'calculate_columns' ), 100000 - 1000, 1 );
		remove_filter( 'woocommerce_form_field_args', array( $this, 'calculate_columns' ), 100000 );
		remove_filter( 'woocommerce_form_field_args', array( $this, 'cfw_form_field_args' ), 100000 );
		remove_filter( 'woocommerce_form_field', array( $this, 'remove_extraneous_field_classes' ), 100000 );
		remove_filter( 'woocommerce_form_field', array( $this, 'add_select_container_class' ), 200000 );
		remove_filter( 'woocommerce_form_field', array( $this, 'cleanup_space_between_checkbox_input_and_text' ), 200000, 3 );
		remove_filter( 'woocommerce_form_field', array( $this, 'add_before_html' ), 200000 );
		remove_filter( 'woocommerce_form_field_password', array( $this, 'password_field_toggle' ), 200000, 3 );
	}

	public function calculate_columns( $args ): array {
		if ( ! isset( $args['class'] ) || ! is_array( $args['class'] ) ) {
			$args['class'] = ! empty( $args['class'] ) ? array( $args['class'] ) : array();
		}

		// Calculate columns
		if ( ! isset( $args['columns'] ) ) {
			$args['columns'] = 12;

			if ( in_array( 'form-row-first', $args['class'], true ) || in_array( 'form-row-last', $args['class'], true ) ) {
				$args['columns'] = 6;
			}
		}

		if ( in_array( 'col-lg-3', $args['class'], true ) ) {
			$args['columns'] = 3;
		}

		if ( in_array( 'col-lg-4', $args['class'], true ) ) {
			$args['columns'] = 4;
		}

		if ( in_array( 'col-lg-6', $args['class'], true ) ) {
			$args['columns'] = 6;
		}

		if ( in_array( 'col-lg-8', $args['class'], true ) ) {
			$args['columns'] = 8;
		}

		if ( in_array( 'col-lg-9', $args['class'], true ) ) {
			$args['columns'] = 9;
		}

		// Add column class
		$column_class = 'col-lg-' . $args['columns'];

		if ( ! in_array( $column_class, $args['class'], true ) ) {
			$args['class'][] = $column_class;
		}

		return $args;
	}

	/**
	 * Pre-process form field arguments for our pages
	 *
	 * @param mixed $args The arguments.
	 * @param mixed $key  The key.
	 * @return array
	 */
	public function cfw_form_field_args( $args = array(), $key = null ): array {
		// Handle input classes
		if ( is_string( $args['input_class'] ) ) {
			$args['input_class'] = array( $args['input_class'] );
		}

		if ( is_string( $args['label_class'] ) ) {
			$args['label_class'] = array( $args['label_class'] );
		}

		// Add field type class
		$args['class'][] = 'cfw-' . $args['type'] . '-input';

		// Part of operation stop sorting our fields!
		$args['priority'] = '';

		if ( in_array( $args['type'], $this->get_checkbox_like_field_types(), true ) ) {
			$args['class'][] = 'cfw-check-input';
		}

		$label_style = $this->get_label_style();

		/**
		 * The non-floating label field types
		 *
		 * @since 6.2.3
		 * @param array $field_types The nonfloating label field types
		 */
		if ( 'floating' === $label_style && ! in_array( $args['type'], apply_filters( 'cfw_non_floating_label_field_types', array( 'checkbox', 'radio' ) ), true ) ) {
			$args['label_class'][] = 'cfw-floatable-label';
		}

		// Add generic wrap
		$args['class'][] = 'cfw-input-wrap';

		$value = WC()->checkout()->get_value( $key );

		if ( ! is_null( $value ) ) {
			$args['custom_attributes']['data-persist'] = 'false';
		}

		// Set saved value
		$args['custom_attributes']['data-saved-value'] = $value ?? 'CFW_EMPTY';

		$label_for_placeholder = wp_strip_all_tags( $args['label'] );
		$args['placeholder']   = ! empty( $args['placeholder'] ) ? $args['placeholder'] : trim( preg_replace( '/\s*\*+$/', '', $label_for_placeholder ) );

		/**
		 * Whether to append optional to field placeholder
		 *
		 * @since 6.2.3
		 * @deprecated 10.1.13
		 * @param bool $append Whether to append optional to field placeholder
		 * @param mixed $key  The key.
		 *
		 */
		$suppress_placeholder = apply_filters_deprecated( 'cfw_form_field_append_optional_to_placeholder', array( isset( $args['suppress_optional_suffix'] ), $key  ), 'CheckoutWC 10.1.13', 'cfw_form_field_suppress_optional_in_placeholder' );

		/**
		 * Whether to suppress 'optional' from field placeholder
		 *
		 * @since 10.1.13
		 * @param bool $append Whether to suppress optional from field placeholder
		 * @param mixed $key  The key.
		 */
		$suppress_placeholder = apply_filters( 'cfw_form_field_suppress_optional_in_placeholder', $suppress_placeholder, $key );
		if ( ! $args['required'] && ! isset( $args['custom_attributes']['readonly'] ) && false === stripos( $args['placeholder'], __( 'optional', 'woocommerce' ) ) && ! $suppress_placeholder ) {
			$args['placeholder'] .= ' (' . __( 'optional', 'woocommerce' ) . ')';
		}

		if ( ! $args['required'] ) {
			// Prevent doubled optional in labels - woocommerce_form_field() adds it
			$args['label'] = str_ireplace( '(' . __( 'optional', 'woocommerce' ) . ')', '', $args['label'] );
		}

		// Make sure we have a default option
		if ( 'select' === $args['type'] && is_array( $args['options'] ) && ! empty( $args['options'] ) ) {
			// Reset options array to first element.
			reset( $args['options'] );

			if ( key( $args['options'] ) !== '' ) {
				$args['options'] = array( '' => __( 'Choose an option', 'woocommerce' ) ) + $args['options'];
			}

			/**
			 * Filters the select field options for edge cases
			 *
			 * @param array $options The select field options
			 * @param array $args The field arguments
			 * @param string $key The field key
			 * @since 7.4.0
			 */
			$args['options'] = apply_filters( 'cfw_select_field_options', $args['options'], $args, $key );
		}

		if ( 'floating' === $label_style && ( 'select' === $args['type'] || ! empty( $args['value'] ) ) ) {
			$args['class'][] = 'cfw-label-is-floated';
		}

		return $this->maybe_add_parsley_attributes( $args );
	}

	/**
	 * Strip classes that we don't want on our fields from woocommerce_form_field output
	 *
	 * @param array $field The field.
	 * @return array|string|string[]
	 */
	public function remove_extraneous_field_classes( $field ) {
		$classes_to_remove = array( 'form-row-first', 'form-row-last', 'form-row-wide' );

		foreach ( $classes_to_remove as $class ) {
			if ( strpos( $field, $class ) !== false ) {
				// Cleanup <class><space> and <class>
				$field = str_replace( $class . ' ', '', $field );
				$field = str_replace( $class, '', $field );
			}
		}

		return $field;
	}

	/**
	 * Add cfw-select-input to the wrap for select fields
	 *
	 * @param array $field The field.
	 * @return array|string|string[]
	 */
	public function add_select_container_class( $field ) {
		if ( stripos( $field, '<select' ) !== false ) {
			$field = str_replace( 'form-row', 'form-row cfw-select-input', $field );
		}

		return $field;
	}

	/**
	 * Cleanup space between checkbox and label
	 *
	 * @param string $field The field.
	 * @param mixed  $key    The key.
	 * @param mixed  $args   The arguments.
	 * @return array|string|string[]
	 */
	public function cleanup_space_between_checkbox_input_and_text( string $field, $key = null, $args = array() ): string {
		if ( ! in_array( $args['type'], $this->get_checkbox_like_field_types(), true ) ) {
			return $field;
		}

		$field = preg_replace( '@(<input.+type="checkbox".+/>)\s@', '$1', $field );

		return preg_replace( '@(</span>)\s@', '$1', $field );
	}

	/**
	 * Get the current field label style (floating or normal) for the active template.
	 *
	 * @return string 'floating' or 'normal'
	 */
	private function get_label_style(): string {
		if ( ! function_exists( 'cfw_get_active_template' ) ) {
			return 'floating';
		}

		$active_template = cfw_get_active_template();
		if ( ! $active_template ) {
			return 'floating';
		}

		$style = SettingsManager::instance()->get_setting( 'label_style', array( $active_template->get_slug() ) );

		return ( $style === 'normal' ) ? 'normal' : 'floating';
	}

	public function add_before_html( $field, $key = null, $args = array() ) {
		if ( $args['before_html'] ?? false ) {
			$field = $args['before_html'] . $field;
		}

		return $field;
	}

	public function add_after_html( $field, $key = null, $args = array() ) {
		if ( $args['after_html'] ?? false ) {
			$field .= $args['after_html'];
		}

		return $field;
	}

	public function maybe_add_parsley_attributes( $args = array() ): array {
		if ( ! $args['required'] ) {
			return $args;
		}

		if ( 'hidden' === $args['type'] ) {
			return $args;
		}

		$args['custom_attributes']['data-parsley-required'] = 'true';

		$current_tab = cfw_get_current_tab();

		// Set parsley group
		if ( ! empty( $current_tab ) && empty( $args['custom_attributes']['data-parsley-group'] ) ) {
			$args['custom_attributes']['data-parsley-group'] = $current_tab;
		}

		return $args;
	}

	/**
	 * Get the checkbox like field types
	 *
	 * @return string[]
	 */
	public function get_checkbox_like_field_types(): array {
		/**
		 * The field type that are like checkboxes
		 *
		 * @since 7.0.10
		 * @param string[] $field_types The field types
		 */
		return apply_filters( 'cfw_checkbox_like_field_types', $this->checkbox_like_field_types );
	}

	/**
	 * Return password field toggle HTML
	 *
	 * @param string $field The field.
	 * @param mixed  $key The key.
	 * @param mixed  $args The arguments.
	 * @param mixed  $value The value.
	 *
	 * @return string
	 */
	public function password_field_toggle( $field, $key = null, $args = array(), $value = null ): string {
		$eye_open = '<svg xmlns="http://www.w3.org/2000/svg" class="cfw-eye-open" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>';
		$eye_shut = '<svg xmlns="http://www.w3.org/2000/svg" class="cfw-eye-shut" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>';

		$wrap = '<a class="cfw-password-toggle cfw-password-eye-open" tabindex="-1" href="javascript:">' . $eye_open . $eye_shut . '</a>';

		return str_replace( '<input', "{$wrap}<input", $field );
	}
}
