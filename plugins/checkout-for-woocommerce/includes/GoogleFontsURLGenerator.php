<?php
namespace Objectiv\Plugins\Checkout;

class GoogleFontsURLGenerator {

	private $fonts   = array();
	private $display = 'swap';
	private $text    = null;
	private $baseUrl = 'https://fonts.googleapis.com/css2';

	/**
	 * Add a font family with optional weight and style configurations
	 *
	 * @param string            $family Font family name.
	 * @param array|string|null $weights Single weight, array of weights, or weight range (e.g., '200..900').
	 * @param bool              $italic Include italic variant.
	 *
	 * @return self
	 */
	public function addFont( string $family, $weights = null, bool $italic = false ): GoogleFontsURLGenerator {
		if ( ! isset( $this->fonts[ $family ] ) ) {
			$this->fonts[ $family ] = array(
				'variants' => array(),
			);
		}

		// Handle different weight formats
		if ( null === $weights ) {
			// Default weight is 400
			$this->addVariant( $family, null, $italic );
		} elseif ( is_string( $weights ) && strpos( $weights, '..' ) !== false ) {
			// Weight range (e.g., '200..900')
			$this->addVariant( $family, $weights, $italic );
		} elseif ( is_array( $weights ) ) {
			// Multiple specific weights
			foreach ( $weights as $weight ) {
				$this->addVariant( $family, $weight, $italic );
			}
		} else {
			// Single weight
			$this->addVariant( $family, $weights, $italic );
		}

		return $this;
	}

	/**
	 * Add a specific variant to a font family
	 *
	 * @param string      $family Font family name.
	 * @param string|null $weight Weight value or range.
	 * @param bool        $italic Include italic variant.
	 */
	private function addVariant( string $family, ?string $weight, bool $italic ) {
		$key = ( $italic ? 'i' : 'n' ) . ':' . ( $weight ? $weight : '400' );

		$this->fonts[ $family ]['variants'][ $key ] = array(
			'weight' => $weight ? $weight : '400',
			'italic' => $italic,
		);
	}

	/**
	 * Add multiple fonts at once
	 *
	 * @param array $fonts Array of font configurations.
	 * @return self
	 */
	public function addFonts( array $fonts ): GoogleFontsURLGenerator {
		foreach ( $fonts as $font ) {

			// 1) Simple string font name?
			if ( is_string( $font ) ) {
				$this->addFont( $font );
				continue;
			}

			// 2) Otherwise it must be an array configuration
			$family  = $font['family'] ?? $font[0] ?? null;
			$weights = $font['weights'] ?? $font['weight'] ?? $font[1] ?? null;
			$italic  = $font['italic'] ?? $font[2] ?? false;

			// 3) If we still don’t have a family, skip it
			if ( ! $family ) {
				continue;
			}

			$this->addFont( $family, $weights, $italic );
		}

		return $this;
	}

	/**
	 * Set the font-display property
	 *
	 * @param string $display Display value (auto, block, swap, fallback, optional).
	 *
	 * @return self
	 */
	public function setDisplay( string $display ): GoogleFontsURLGenerator {
		$validValues = array( 'auto', 'block', 'swap', 'fallback', 'optional' );

		if ( in_array( $display, $validValues, true ) ) {
			$this->display = $display;
		}

		return $this;
	}

	/**
	 * Set text optimization parameter
	 *
	 * @param string $text Text to optimize for.
	 *
	 * @return self
	 */
	public function setText( string $text ): GoogleFontsURLGenerator {
		$this->text = $text;

		return $this;
	}

	/**
	 * Generate the Google Fonts URL
	 *
	 * @return string
	 */
	public function getUrl(): string {
		if ( empty( $this->fonts ) ) {
			return '';
		}

		$params = array();

		// Build family parameters
		foreach ( $this->fonts as $family => $data ) {
			$familyParam = $this->buildFamilyParam( $family, $data['variants'] );
			if ( $familyParam ) {
				$params[] = 'family=' . $familyParam;
			}
		}

		// Add display parameter
		if ( $this->display && 'auto' !== $this->display ) {
			$params[] = 'display=' . $this->display;
		}

		// Add text parameter
		if ( $this->text ) {
			$params[] = 'text=' . rawurlencode( $this->text );
		}

		if ( empty( $params ) ) {
			return '';
		}

		return $this->baseUrl . '?' . implode( '&', $params );
	}

	/**
	 * Build the family parameter for a single font
	 *
	 * @param string $family Font family name.
	 * @param array  $variants Font variants.
	 *
	 * @return string
	 */
	private function buildFamilyParam( string $family, array $variants ): string {
		$familyName = str_replace( ' ', '+', $family );

		if ( empty( $variants ) ) {
			return $familyName;
		}

		// Group variants by type
		$groups = $this->groupVariants( $variants );

		// If only default variant (regular 400)
		if ( count( $groups ) === 1 && isset( $groups['simple'] ) &&
			count( $groups['simple'] ) === 1 &&
			! $groups['simple'][0]['italic'] &&
			'400' === $groups['simple'][0]['weight'] ) {
			return $familyName;
		}

		// Build axis specification
		$spec = $this->buildAxisSpec( $groups );

		if ( empty( $spec ) ) {
			return $familyName;
		}

		return $familyName . ':' . $spec;
	}

	/**
	 * Group variants by type (ranges vs individual weights)
	 *
	 * @param array $variants The variants to group.
	 *
	 * @return array
	 */
	private function groupVariants( array $variants ): array {
		$groups = array(
			'ranges' => array(),
			'simple' => array(),
		);

		foreach ( $variants as $variant ) {
			if ( strpos( $variant['weight'], '..' ) !== false ) {
				$groups['ranges'][] = $variant;
			} else {
				$groups['simple'][] = $variant;
			}
		}

		return array_filter( $groups );
	}

	/**
	 * Build the axis specification string
	 *
	 * @param array $groups Grouped variants.
	 *
	 * @return string
	 */
	private function buildAxisSpec( array $groups ): string {
		$hasItalic  = false;
		$hasRegular = false;
		$specs      = array();

		// 1. Check if we need the 'ital' axis and if any regular styles are present.
		$allVariants = array_merge( $groups['ranges'] ?? array(), $groups['simple'] ?? array() );
		foreach ( $allVariants as $variant ) {
			if ( $variant['italic'] ) {
				$hasItalic = true;
			} else {
				$hasRegular = true;
			}
		}

		// 2. Handle ranges
		if ( ! empty( $groups['ranges'] ) ) {
			foreach ( $groups['ranges'] as $variant ) {
				if ( $hasItalic ) {
					$italValue = $variant['italic'] ? '1' : '0';
					$specs[]   = $italValue . ',' . $variant['weight'];
				} else {
					$specs[] = $variant['weight'];
				}
			}
		}

		// 3. Handle individual weights
		if ( ! empty( $groups['simple'] ) ) {
			$regularWeights = array();
			$italicWeights  = array();

			foreach ( $groups['simple'] as $variant ) {
				if ( $variant['italic'] ) {
					$italicWeights[] = $variant['weight'];
				} else {
					$regularWeights[] = $variant['weight'];
				}
			}

			// *** START FIX ***
			// API WORKAROUND: If a request uses the 'ital' axis, it must include at least one
			// non-italic style (ital=0). If only italic styles were requested, we must add
			// a default regular weight to ensure the generated URL is valid.
			if ( $hasItalic && ! $hasRegular ) {
				$regularWeights[] = '400';
			}
			// *** END FIX ***

			sort( $regularWeights );
			sort( $italicWeights );

			if ( $hasItalic ) {
				foreach ( $regularWeights as $weight ) {
					$specs[] = '0,' . $weight;
				}
				foreach ( $italicWeights as $weight ) {
					$specs[] = '1,' . $weight;
				}
			} else {
				$specs = array_merge( $specs, $regularWeights );
			}
		}

		if ( empty( $specs ) ) {
			return '';
		}

		// 4. Build final axis string
		$axes = $hasItalic ? 'ital,wght' : 'wght';

		return $axes . '@' . implode( ';', $specs );
	}


	/**
	 * Reset all fonts and settings
	 *
	 * @return self
	 */
	public function reset(): GoogleFontsURLGenerator {
		$this->fonts   = array();
		$this->display = 'swap';
		$this->text    = null;

		return $this;
	}

	/**
	 * Magic method to get URL as string
	 *
	 * @return string
	 */
	public function __toString() {
		return $this->getUrl();
	}
}
