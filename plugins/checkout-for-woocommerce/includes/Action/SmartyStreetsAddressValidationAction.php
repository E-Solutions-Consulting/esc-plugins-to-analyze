<?php

namespace Objectiv\Plugins\Checkout\Action;

use Exception;
use CheckoutWC\SmartyStreets\PhpSdk\ClientBuilder;
use CheckoutWC\SmartyStreets\PhpSdk\Exceptions\SmartyException;
use CheckoutWC\SmartyStreets\PhpSdk\International_Street\Client as InternationalStreetApiClient;
use CheckoutWC\SmartyStreets\PhpSdk\International_Street\Lookup;
use CheckoutWC\SmartyStreets\PhpSdk\StaticCredentials;
use CheckoutWC\SmartyStreets\PhpSdk\US_Street\Candidate;
use CheckoutWC\SmartyStreets\PhpSdk\US_Street\Client as USStreetApiClient;

/**
 * Class SmartyStreetsAddressValidationAction
 *
 * @link checkoutwc.com
 * @since 1.0.0
 * @package Objectiv\Plugins\Checkout\Action
 */
class SmartyStreetsAddressValidationAction extends CFWAction {
	protected $smartystreets_auth_id;
	protected $smartystreets_auth_token;

	public function __construct( $smartystreets_auth_id, $smartystreets_auth_token ) {
		parent::__construct( 'cfw_smartystreets_address_validation' );

		$this->smartystreets_auth_id    = $smartystreets_auth_id;
		$this->smartystreets_auth_token = $smartystreets_auth_token;
	}

	public function action() {
		$original_address = array_filter( $_POST['address'] ?? array() ); // phpcs:ignore

		/**
		 * Codes:
		 * - 1, good address with no suggested fixes
		 * - 2, bad address with matching suggested address
		 * - 3, bad address with no matching suggestion
		 */
		try {
			$suggested_address = array_filter( $this->get_suggested_address( $original_address ) );

			// Keep only the keys that also exist in $original_address to prevent false positives
			$suggested_address = array_intersect_key( $suggested_address, $original_address );

			$changed_component_keys = array_keys( array_diff_assoc( $suggested_address, $original_address ) );

			// address_2 is not trustworthy for international addresses,
			// so if nothing else has changed, just treat it like it's golden
			if ( count( $changed_component_keys ) === 1 && 'US' !== $suggested_address['country'] && in_array( 'address_2', $changed_component_keys, true ) ) {
				$this->out(
					array(
						'code'       => 1,
						'form'       => '',
						'message'    => '',
						'components' => array(),
					)
				);
			}

			if ( empty( $changed_component_keys ) ) {
				$this->out(
					array(
						'code'       => 1,
						'form'       => '',
						'message'    => '',
						'components' => array(),
					)
				);
			}

			cfw_debug_log( 'Original address: ' . print_r( $original_address, true ) ); // phpcs:ignore
			cfw_debug_log( 'Suggested address: ' . print_r( $suggested_address, true ) ); // phpcs:ignore

			$code = 2;

			$this->out(
				array(
					'code'       => $code,
					'form'       => $this->get_form(
						$code,
						$this->format_suggested_address( $original_address, $suggested_address ),
						stripslashes( WC()->countries->get_formatted_address( $original_address ) ),
						__( 'Use Recommended', 'checkout-wc' ),
						__( 'Use Your Address', 'checkout-wc' )
					),
					'message'    => '',
					'components' => $suggested_address,
				)
			);
		} catch ( Exception $ex ) {
			$code = 3;

			// This is just for debug - we don't show this
			$this->out(
				array(
					'code'       => $code,
					'form'       => $this->get_form(
						$code,
						'',
						stripslashes( WC()->countries->get_formatted_address( $original_address ) ),
						__( 'Reenter Address', 'checkout-wc' ),
						__( 'Use Existing Address', 'checkout-wc' )
					),
					'message'    => $ex->getMessage(),
					'components' => array(),
				)
			);
		}
	}

	protected function format_suggested_address( array $original_address, array $suggested_address ) {
		$changed_component_keys = array_keys( array_diff_assoc( $suggested_address, $original_address ) );

		$poisoned_address = $suggested_address;
		$replace_start    = 'checkoutwc_0';
		$replace_end      = 'checkoutwc_1';

		foreach ( $changed_component_keys as $key ) {
			$poisoned_address[ $key ] = "{$replace_start}{$suggested_address[$key]}{$replace_end}";
		}

		$output_address = WC()->countries->get_formatted_address( $poisoned_address );
		$output_address = str_ireplace( $replace_start, '<span style="color:red">', $output_address );

		return str_ireplace( $replace_end, '</span>', $output_address );
	}

	/**
	 * Get suggested address
	 *
	 * @param array $address The input address.
	 * @throws SmartyException|Exception If the address cannot be suggested.
	 */
	protected function get_suggested_address( array $address ): array {
		$credentials = new StaticCredentials( $this->smartystreets_auth_id, $this->smartystreets_auth_token );
		$builder     = new ClientBuilder( $credentials );

		$builder->retryAtMost( 0 )->withMaxTimeout( 3000 );

		/**
		 * Filter the address before it's sent to SmartyStreets
		 *
		 * @param array $address The address to be sent to SmartyStreets
		 *
		 * @since 7.10.3
		 */
		$address = apply_filters( 'cfw_smarty_address_validation_address', $address );

		// Whenever we add another condition to this tree it's time to break this out into OO with factory.
		if ( 'US' === $address['country'] ) {
			return $this->getDomesticAddressSuggestion( $address, $builder->buildUsStreetApiClient() );
		} elseif ( 'GB' === $address['country'] ) {
			return $this->getUKAddressSuggestion( $address, $builder->buildInternationalStreetApiClient() );
		} elseif ( 'CA' === $address['country'] ) {
			return $this->getCAAddressSuggestion( $address, $builder->buildInternationalStreetApiClient() );
		} else {
			return $this->getInternationalAddressSuggestion( $address, $builder->buildInternationalStreetApiClient() );
		}
	}

	public function get_form( $code, $address, $original, $suggested_button_label, $user_button_label ) {
		ob_start();
		?>
		<?php if ( 2 === $code ) : ?>
			<h2 id="cfw-smarty-modal-title" class="cfw-smarty-matched">
				<?php esc_html_e( 'Use recommended address instead?', 'checkout-wc' ); ?>
			</h2>
			<h4 id="cfw-smarty-modal-subtitle" class="cfw-small cfw-smarty-matched">
				<?php esc_html_e( 'We\'re unable to verify your address, but found a close match.', 'checkout-wc' ); ?>
			</h4>
		<?php else : ?>
			<h2 id="cfw-smarty-modal-title" class="cfw-smarty-unmatched">
				<?php esc_html_e( 'We are unable to verify your address.', 'checkout-wc' ); ?>
			</h2>

			<h4 id="cfw-smarty-modal-subtitle" class="cfw-small cfw-smarty-unmatched">
				<?php esc_html_e( 'Please confirm you would like to use this address or try again.', 'checkout-wc' ); ?>
			</h4>
		<?php endif; ?>

		<div class="cfw-smartystreets-option-wrap">
			<h4>
				<label>
					<?php esc_html_e( 'You Entered', 'checkout-wc' ); ?>
				</label>
			</h4>

			<p class="cfw-smartystreets-user-address">
				<?php echo wp_kses_post( $original ); ?>
			</p>
		</div>

		<div class="cfw-smartystreets-option-wrap cfw-smarty-matched">
			<h4>
				<label>
					<?php esc_html_e( 'Recommended', 'checkout-wc' ); ?>
				</label>
			</h4>

			<p class="cfw-smartystreets-suggested-address">
				<?php echo wp_kses_post( $address ); ?>
			</p>
		</div>

		<p class="cfw-smartystreets-button-wrap">
			<a href="#" class="cfw-smartystreets-button cfw-primary-btn cfw-smartystreets-suggested-address-button">
				<?php echo esc_html( $suggested_button_label ); ?>
			</a>
		</p>

		<p class="cfw-smartystreets-button-wrap">
			<a href="#" class="cfw-smartystreets-button cfw-smartystreets-user-address-button">
				<?php echo esc_html( $user_button_label ); ?>
			</a>
		</p>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get domestic address suggestion
	 *
	 * @param array             $address The input address.
	 * @param USStreetApiClient $client  The US Street API client.
	 *
	 * @return array
	 * @throws SmartyException|Exception If the address cannot be suggested.
	 */
	public function getDomesticAddressSuggestion( array $address, USStreetApiClient $client ): array {
		$lookup = new \CheckoutWC\SmartyStreets\PhpSdk\US_Street\Lookup();

		$lookup->setStreet( $address['address_1'] );
		$lookup->setStreet2( $address['address_2'] );
		$lookup->setCity( $address['city'] );
		$lookup->setState( $address['state'] );
		$lookup->setZipcode( $address['postcode'] );
		$lookup->setMaxCandidates( 1 );
		$lookup->setMatchStrategy( 'invalid' );

		$client->sendLookup( $lookup ); // The candidates are also stored in the lookup's 'result' field.

		/** @var Candidate $first_candidate */
		$first_candidate = $lookup->getResult()[0];

		if ( null === $first_candidate->getAnalysis()->getDpvMatchCode() ) {
			throw new Exception( esc_html__( 'We are unable to verify your address.', 'checkout-wc' ), 3 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		$suggested_address   = $first_candidate->getDeliveryLine1();
		$suggested_address_2 = $first_candidate->getDeliveryLine2();
		$suggested_postcode  = $first_candidate->getComponents()->getZipcode();
		$suggested_state     = $first_candidate->getComponents()->getStateAbbreviation();
		$suggested_city      = $first_candidate->getComponents()->getCityName();

		/**
		 * Filter whether to use the zip4 code
		 *
		 * @param bool $use_zip4 Whether to use the zip4 code
		 *
		 * @since 8.2.26
		 */
		if ( apply_filters( 'cfw_smarty_use_zip4', false ) ) {
			$suggested_postcode .= '-' . $first_candidate->getComponents()->getPlus4Code();
		}

		return array(
			'address_1' => $suggested_address,
			'address_2' => $suggested_address_2,
			'city'      => $suggested_city,
			'state'     => $suggested_state,
			'postcode'  => $suggested_postcode,
			'country'   => 'PR' === $suggested_state ? $suggested_state : 'US',
			'company'   => $address['company'],
		);
	}

	/**
	 * @param array                        $address The input address.
	 * @param InternationalStreetApiClient $client  The International Street API client.
	 *
	 * @return array
	 * @throws SmartyException|Exception If the address cannot be suggested.
	 */
	public function getInternationalAddressSuggestion( array $address, InternationalStreetApiClient $client ): array {
		$lookup = new Lookup();

		$lookup->setInputId( '0' );
		$lookup->setAddress1( $address['address_1'] );
		$lookup->setAddress2( $address['address_2'] );
		$lookup->setLocality( $address['city'] );
		$lookup->setAdministrativeArea( $address['state'] );
		$lookup->setCountry( $address['country'] );
		$lookup->setPostalCode( $address['postcode'] );

		$client->sendLookup( $lookup ); // The candidates are also stored in the lookup's 'result' field.

		/** @var \CheckoutWC\SmartyStreets\PhpSdk\International_Street\Candidate $first_candidate */
		$first_candidate = $lookup->getResult()[0];
		$analysis        = $first_candidate->getAnalysis();
		$precision       = $analysis->getAddressPrecision();

		if ( 'Premise' !== $precision && 'DeliveryPoint' !== $precision ) {
			throw new Exception( esc_html__( 'We are unable to verify your address.', 'checkout-wc' ), 3 );
		}

		$suggested_address = $first_candidate->getAddress1();
		$iso3              = $first_candidate->getComponents()->getCountryIso3();
		$suggested_country = $this->get_iso_2( $iso3 );
		$postcode_extra    = $first_candidate->getComponents()->getPostalCodeExtra();
		$postcode_suffix   = empty( $postcode_extra ) ? '' : '-' . $postcode_extra;
		$postcode_short    = $first_candidate->getComponents()->getPostalCodeShort();
		$suggested_zip     = $postcode_short . $postcode_suffix;
		$suggested_state   = $first_candidate->getComponents()->getAdministrativeArea();
		$suggested_city    = $first_candidate->getComponents()->getLocality();

		return array(
			'house_number' => $first_candidate->getComponents()->getPremise(),
			'street_name'  => $first_candidate->getComponents()->getThoroughfare(),
			'address_1'    => $suggested_address,
			'company'      => $address['company'],
			'city'         => $suggested_city,
			'country'      => 'PR' === $suggested_state ? $suggested_state : $suggested_country,
			'state'        => $suggested_state,
			'postcode'     => $suggested_zip,
		);
	}

	/**
	 * Get UK address suggestion
	 *
	 * @param array                        $address The input address.
	 * @param InternationalStreetApiClient $client  The International Street API client.
	 *
	 * @return array
	 * @throws SmartyException|Exception If the address cannot be suggested.
	 */
	public function getUKAddressSuggestion( array $address, InternationalStreetApiClient $client ): array {
		$lookup = new Lookup();

		$lookup->setInputId( '0' );
		$lookup->setAddress1( $address['address_1'] );
		$lookup->setAddress2( $address['address_2'] );
		$lookup->setLocality( $address['city'] );
		$lookup->setAdministrativeArea( $address['state'] );
		$lookup->setCountry( $address['country'] );
		$lookup->setPostalCode( $address['postcode'] );

		$client->sendLookup( $lookup ); // The candidates are also stored in the lookup's 'result' field.

		/** @var \CheckoutWC\SmartyStreets\PhpSdk\International_Street\Candidate $first_candidate */
		$first_candidate = $lookup->getResult()[0];
		$analysis        = $first_candidate->getAnalysis();
		$precision       = $analysis->getAddressPrecision();

		if ( 'Premise' !== $precision && 'DeliveryPoint' !== $precision ) {
			throw new Exception( esc_html__( 'We are unable to verify your address.', 'checkout-wc' ), 3 );
		}

		$suggested_address = $first_candidate->getAddress1();
		$iso3              = $first_candidate->getComponents()->getCountryIso3();
		$suggested_country = $this->get_iso_2( $iso3 );
		$postcode_extra    = $first_candidate->getComponents()->getPostalCodeExtra();
		$postcode_suffix   = empty( $postcode_extra ) ? '' : ' - ' . $postcode_extra;
		$postcode_short    = $first_candidate->getComponents()->getPostalCodeShort();
		$suggested_zip     = $postcode_short . $postcode_suffix;
		$suggested_state   = $first_candidate->getComponents()->getAdministrativeArea();
		$suggested_city    = $first_candidate->getComponents()->getLocality();

		return array(
			'house_number' => $first_candidate->getComponents()->getPremise(),
			'street_name'  => $first_candidate->getComponents()->getThoroughfare(),
			'address_1'    => $suggested_address,
			'company'      => $address['company'],
			'city'         => $suggested_city,
			'country'      => $suggested_country,
			'state'        => $suggested_state,
			'postcode'     => $suggested_zip,
		);
	}

	/**
	 * @param array                        $address The input address.
	 * @param InternationalStreetApiClient $client  The International Street API client.
	 *
	 * @return array
	 * @throws SmartyException|Exception If the address cannot be suggested.
	 */
	public function getCAAddressSuggestion( array $address, InternationalStreetApiClient $client ): array {
		$lookup = new Lookup();

		$lookup->setInputId( '0' );
		$lookup->setAddress1( $address['address_1'] );
		$lookup->setAddress2( $address['address_2'] );
		$lookup->setLocality( $address['city'] );
		$lookup->setAdministrativeArea( $address['state'] );
		$lookup->setCountry( $address['country'] );
		$lookup->setPostalCode( $address['postcode'] );

		$client->sendLookup( $lookup ); // The candidates are also stored in the lookup's 'result' field.

		/** @var \CheckoutWC\SmartyStreets\PhpSdk\International_Street\Candidate $first_candidate */
		$first_candidate = $lookup->getResult()[0];
		$analysis        = $first_candidate->getAnalysis();
		$precision       = $analysis->getAddressPrecision();

		if ( 'Premise' !== $precision && 'DeliveryPoint' !== $precision ) {
			throw new Exception( esc_html__( 'We are unable to verify your address.', 'checkout-wc' ), 3 );
		}

		$suggested_address = $first_candidate->getAddress1();
		$iso3              = $first_candidate->getComponents()->getCountryIso3();
		$suggested_country = $this->get_iso_2( $iso3 );
		$postcode_extra    = $first_candidate->getComponents()->getPostalCodeExtra();
		$postcode_suffix   = empty( $postcode_extra ) ? '' : ' ' . $postcode_extra;
		$postcode_short    = $first_candidate->getComponents()->getPostalCodeShort();
		$suggested_zip     = $postcode_short . $postcode_suffix;
		$suggested_state   = $first_candidate->getComponents()->getAdministrativeArea();
		$suggested_city    = $first_candidate->getComponents()->getLocality();

		return array(
			'house_number' => $first_candidate->getComponents()->getPremise(),
			'street_name'  => $first_candidate->getComponents()->getThoroughfare(),
			'address_1'    => $suggested_address,
			'company'      => $address['company'],
			'city'         => $suggested_city,
			'country'      => $suggested_country,
			'state'        => $suggested_state,
			'postcode'     => $suggested_zip,
		);
	}

	private function get_iso_2( string $iso3 ): string {
		$map = array(
			'AFG' => 'AF', // Afghanistan
			'ALB' => 'AL', // Albania
			'DZA' => 'DZ', // Algeria
			'ASM' => 'AS', // American Samoa
			'AND' => 'AD', // Andorra
			'AGO' => 'AO', // Angola
			'AIA' => 'AI', // Anguilla
			'ATA' => 'AQ', // Antarctica
			'ATG' => 'AG', // Antigua and Barbuda
			'ARG' => 'AR', // Argentina
			'ARM' => 'AM', // Armenia
			'ABW' => 'AW', // Aruba
			'AUS' => 'AU', // Australia
			'AUT' => 'AT', // Austria
			'AZE' => 'AZ', // Azerbaijan
			'BHS' => 'BS', // Bahamas (the)
			'BHR' => 'BH', // Bahrain
			'BGD' => 'BD', // Bangladesh
			'BRB' => 'BB', // Barbados
			'BLR' => 'BY', // Belarus
			'BEL' => 'BE', // Belgium
			'BLZ' => 'BZ', // Belize
			'BEN' => 'BJ', // Benin
			'BMU' => 'BM', // Bermuda
			'BTN' => 'BT', // Bhutan
			'BOL' => 'BO', // Bolivia (Plurinational State of)
			'BES' => 'BQ', // Bonaire, Sint Eustatius and Saba
			'BIH' => 'BA', // Bosnia and Herzegovina
			'BWA' => 'BW', // Botswana
			'BVT' => 'BV', // Bouvet Island
			'BRA' => 'BR', // Brazil
			'IOT' => 'IO', // British Indian Ocean Territory (the)
			'BRN' => 'BN', // Brunei Darussalam
			'BGR' => 'BG', // Bulgaria
			'BFA' => 'BF', // Burkina Faso
			'BDI' => 'BI', // Burundi
			'CPV' => 'CV', // Cabo Verde
			'KHM' => 'KH', // Cambodia
			'CMR' => 'CM', // Cameroon
			'CAN' => 'CA', // Canada
			'CYM' => 'KY', // Cayman Islands (the)
			'CAF' => 'CF', // Central African Republic (the)
			'TCD' => 'TD', // Chad
			'CHL' => 'CL', // Chile
			'CHN' => 'CN', // China
			'CXR' => 'CX', // Christmas Island
			'CCK' => 'CC', // Cocos (Keeling) Islands (the)
			'COL' => 'CO', // Colombia
			'COM' => 'KM', // Comoros (the)
			'COD' => 'CD', // Congo (the Democratic Republic of the)
			'COG' => 'CG', // Congo (the)
			'COK' => 'CK', // Cook Islands (the)
			'CRI' => 'CR', // Costa Rica
			'HRV' => 'HR', // Croatia
			'CUB' => 'CU', // Cuba
			'CUW' => 'CW', // Curaçao
			'CYP' => 'CY', // Cyprus
			'CZE' => 'CZ', // Czechia
			'CIV' => 'CI', // Côte d'Ivoire
			'DNK' => 'DK', // Denmark
			'DJI' => 'DJ', // Djibouti
			'DMA' => 'DM', // Dominica
			'DOM' => 'DO', // Dominican Republic (the)
			'ECU' => 'EC', // Ecuador
			'EGY' => 'EG', // Egypt
			'SLV' => 'SV', // El Salvador
			'GNQ' => 'GQ', // Equatorial Guinea
			'ERI' => 'ER', // Eritrea
			'EST' => 'EE', // Estonia
			'SWZ' => 'SZ', // Eswatini
			'ETH' => 'ET', // Ethiopia
			'FLK' => 'FK', // Falkland Islands (the) [Malvinas]
			'FRO' => 'FO', // Faroe Islands (the)
			'FJI' => 'FJ', // Fiji
			'FIN' => 'FI', // Finland
			'FRA' => 'FR', // France
			'GUF' => 'GF', // French Guiana
			'PYF' => 'PF', // French Polynesia
			'ATF' => 'TF', // French Southern Territories (the)
			'GAB' => 'GA', // Gabon
			'GMB' => 'GM', // Gambia (the)
			'GEO' => 'GE', // Georgia
			'DEU' => 'DE', // Germany
			'GHA' => 'GH', // Ghana
			'GIB' => 'GI', // Gibraltar
			'GRC' => 'GR', // Greece
			'GRL' => 'GL', // Greenland
			'GRD' => 'GD', // Grenada
			'GLP' => 'GP', // Guadeloupe
			'GUM' => 'GU', // Guam
			'GTM' => 'GT', // Guatemala
			'GGY' => 'GG', // Guernsey
			'GIN' => 'GN', // Guinea
			'GNB' => 'GW', // Guinea-Bissau
			'GUY' => 'GY', // Guyana
			'HTI' => 'HT', // Haiti
			'HMD' => 'HM', // Heard Island and McDonald Islands
			'VAT' => 'VA', // Holy See (the)
			'HND' => 'HN', // Honduras
			'HKG' => 'HK', // Hong Kong
			'HUN' => 'HU', // Hungary
			'ISL' => 'IS', // Iceland
			'IND' => 'IN', // India
			'IDN' => 'ID', // Indonesia
			'IRN' => 'IR', // Iran (Islamic Republic of)
			'IRQ' => 'IQ', // Iraq
			'IRL' => 'IE', // Ireland
			'IMN' => 'IM', // Isle of Man
			'ISR' => 'IL', // Israel
			'ITA' => 'IT', // Italy
			'JAM' => 'JM', // Jamaica
			'JPN' => 'JP', // Japan
			'JEY' => 'JE', // Jersey
			'JOR' => 'JO', // Jordan
			'KAZ' => 'KZ', // Kazakhstan
			'KEN' => 'KE', // Kenya
			'KIR' => 'KI', // Kiribati
			'PRK' => 'KP', // Korea (the Democratic People's Republic of)
			'KOR' => 'KR', // Korea (the Republic of)
			'KWT' => 'KW', // Kuwait
			'KGZ' => 'KG', // Kyrgyzstan
			'LAO' => 'LA', // Lao People's Democratic Republic (the)
			'LVA' => 'LV', // Latvia
			'LBN' => 'LB', // Lebanon
			'LSO' => 'LS', // Lesotho
			'LBR' => 'LR', // Liberia
			'LBY' => 'LY', // Libya
			'LIE' => 'LI', // Liechtenstein
			'LTU' => 'LT', // Lithuania
			'LUX' => 'LU', // Luxembourg
			'MAC' => 'MO', // Macao
			'MDG' => 'MG', // Madagascar
			'MWI' => 'MW', // Malawi
			'MYS' => 'MY', // Malaysia
			'MDV' => 'MV', // Maldives
			'MLI' => 'ML', // Mali
			'MLT' => 'MT', // Malta
			'MHL' => 'MH', // Marshall Islands (the)
			'MTQ' => 'MQ', // Martinique
			'MRT' => 'MR', // Mauritania
			'MUS' => 'MU', // Mauritius
			'MYT' => 'YT', // Mayotte
			'MEX' => 'MX', // Mexico
			'FSM' => 'FM', // Micronesia (Federated States of)
			'MDA' => 'MD', // Moldova (the Republic of)
			'MCO' => 'MC', // Monaco
			'MNG' => 'MN', // Mongolia
			'MNE' => 'ME', // Montenegro
			'MSR' => 'MS', // Montserrat
			'MAR' => 'MA', // Morocco
			'MOZ' => 'MZ', // Mozambique
			'MMR' => 'MM', // Myanmar
			'NAM' => 'NA', // Namibia
			'NRU' => 'NR', // Nauru
			'NPL' => 'NP', // Nepal
			'NLD' => 'NL', // Netherlands (the)
			'NCL' => 'NC', // New Caledonia
			'NZL' => 'NZ', // New Zealand
			'NIC' => 'NI', // Nicaragua
			'NER' => 'NE', // Niger (the)
			'NGA' => 'NG', // Nigeria
			'NIU' => 'NU', // Niue
			'NFK' => 'NF', // Norfolk Island
			'MNP' => 'MP', // Northern Mariana Islands (the)
			'NOR' => 'NO', // Norway
			'OMN' => 'OM', // Oman
			'PAK' => 'PK', // Pakistan
			'PLW' => 'PW', // Palau
			'PSE' => 'PS', // Palestine, State of
			'PAN' => 'PA', // Panama
			'PNG' => 'PG', // Papua New Guinea
			'PRY' => 'PY', // Paraguay
			'PER' => 'PE', // Peru
			'PHL' => 'PH', // Philippines (the)
			'PCN' => 'PN', // Pitcairn
			'POL' => 'PL', // Poland
			'PRT' => 'PT', // Portugal
			'PRI' => 'PR', // Puerto Rico
			'QAT' => 'QA', // Qatar
			'MKD' => 'MK', // Republic of North Macedonia
			'ROU' => 'RO', // Romania
			'RUS' => 'RU', // Russian Federation (the)
			'RWA' => 'RW', // Rwanda
			'REU' => 'RE', // Réunion
			'BLM' => 'BL', // Saint Barthélemy
			'SHN' => 'SH', // Saint Helena, Ascension and Tristan da Cunha
			'KNA' => 'KN', // Saint Kitts and Nevis
			'LCA' => 'LC', // Saint Lucia
			'MAF' => 'MF', // Saint Martin (French part)
			'SPM' => 'PM', // Saint Pierre and Miquelon
			'VCT' => 'VC', // Saint Vincent and the Grenadines
			'WSM' => 'WS', // Samoa
			'SMR' => 'SM', // San Marino
			'STP' => 'ST', // Sao Tome and Principe
			'SAU' => 'SA', // Saudi Arabia
			'SEN' => 'SN', // Senegal
			'SRB' => 'RS', // Serbia
			'SYC' => 'SC', // Seychelles
			'SLE' => 'SL', // Sierra Leone
			'SGP' => 'SG', // Singapore
			'SXM' => 'SX', // Sint Maarten (Dutch part)
			'SVK' => 'SK', // Slovakia
			'SVN' => 'SI', // Slovenia
			'SLB' => 'SB', // Solomon Islands
			'SOM' => 'SO', // Somalia
			'ZAF' => 'ZA', // South Africa
			'SGS' => 'GS', // South Georgia and the South Sandwich Islands
			'SSD' => 'SS', // South Sudan
			'ESP' => 'ES', // Spain
			'LKA' => 'LK', // Sri Lanka
			'SDN' => 'SD', // Sudan (the)
			'SUR' => 'SR', // Suriname
			'SJM' => 'SJ', // Svalbard and Jan Mayen
			'SWE' => 'SE', // Sweden
			'CHE' => 'CH', // Switzerland
			'SYR' => 'SY', // Syrian Arab Republic
			'TWN' => 'TW', // Taiwan (Province of China)
			'TJK' => 'TJ', // Tajikistan
			'TZA' => 'TZ', // Tanzania, United Republic of
			'THA' => 'TH', // Thailand
			'TLS' => 'TL', // Timor-Leste
			'TGO' => 'TG', // Togo
			'TKL' => 'TK', // Tokelau
			'TON' => 'TO', // Tonga
			'TTO' => 'TT', // Trinidad and Tobago
			'TUN' => 'TN', // Tunisia
			'TUR' => 'TR', // Turkey
			'TKM' => 'TM', // Turkmenistan
			'TCA' => 'TC', // Turks and Caicos Islands (the)
			'TUV' => 'TV', // Tuvalu
			'UGA' => 'UG', // Uganda
			'UKR' => 'UA', // Ukraine
			'ARE' => 'AE', // United Arab Emirates (the)
			'GBR' => 'GB', // United Kingdom of Great Britain and Northern Ireland (the)
			'UMI' => 'UM', // United States Minor Outlying Islands (the)
			'USA' => 'US', // United States of America (the)
			'URY' => 'UY', // Uruguay
			'UZB' => 'UZ', // Uzbekistan
			'VUT' => 'VU', // Vanuatu
			'VEN' => 'VE', // Venezuela (Bolivarian Republic of)
			'VNM' => 'VN', // Viet Nam
			'VGB' => 'VG', // Virgin Islands (British)
			'VIR' => 'VI', // Virgin Islands (U.S.)
			'WLF' => 'WF', // Wallis and Futuna
			'ESH' => 'EH', // Western Sahara
			'YEM' => 'YE', // Yemen
			'ZMB' => 'ZM', // Zambia
			'ZWE' => 'ZW', // Zimbabwe
			'ALA' => 'AX', // Åland Islands
		);

		return $map[ $iso3 ];
	}
}
