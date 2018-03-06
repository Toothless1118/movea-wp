<?php

namespace WPEM;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Smart_Defaults {

	/**
	 * Class constructor
	 *
	 * @param array $geodata
	 */
	public function __construct( $geodata ) {

		if ( ! empty( $geodata['timezone'] ) ) {

			$this->timezone_string( $geodata['timezone'] );

		}

		if ( ! empty( $geodata['country_code'] ) ) {

			$this->start_of_week( $geodata['country_code'] );

		}

	}

	/**
	 * Set the `timezone_string` option
	 *
	 * @param string $timezone
	 */
	private function timezone_string( $timezone ) {

		if ( empty( $timezone ) ) {

			return;

		}

		update_option( 'timezone_string', (string) $timezone );

	}

	/**
	 * Set the `start_of_week` option
	 *
	 * Source: https://savvytime.com/current-week
	 *
	 * @param string $country
	 */
	private function start_of_week( $country ) {

		if ( empty( $country ) ) {

			return;

		}

		switch ( $country ) {

			case 'RA': // Argentina
			case 'BZ': // Belize
			case 'BO': // Bolivia
			case 'BR': // Brazil
			case 'CA': // Canada
			case 'CL': // Chile
			case 'CN': // China
			case 'CO': // Colombia
			case 'CR': // Costa Rica
			case 'KP': // North Korea
			case 'DO': // Dominican Republic
			case 'EC': // Ecuador
			case 'SV': // El Salvador
			case 'GT': // Guatemala
			case 'HN': // Honduras
			case 'HK': // Hong Kong
			case 'IN': // India
			case 'IR': // Iran
			case 'IL': // Israel
			case 'JM': // Jamaica
			case 'KE': // Kenya
			case 'MO': // Macau (Macao)
			case 'MV': // Maldives
			case 'MX': // Mexico
			case 'NI': // Nicaragua
			case 'PA': // Panama
			case 'PE': // Peru
			case 'PH': // Philippines
			case 'PR': // Puerto Rico
			case 'SG': // Singapore
			case 'ZA': // South Africa
			case 'KR': // South Korea
			case 'TW': // Taiwan
			case 'TT': // Trinidad and Tobago
			case 'US': // United States
			case 'VE': // Venezuela
			case 'ZW': // Zimbabwe

				$start_of_week = 0; // Sunday

				break;

			case 'DZ': // Algeria
			case 'BH': // Bahrain
			case 'EG': // Egypt
			case 'IQ': // Iraq
			case 'JO': // Jordan
			case 'KW': // Kuwait
			case 'LY': // Libya
			case 'OM': // Oman
			case 'QA': // Qatar
			case 'SA': // Saudi Arabia
			case 'SY': // Syria
			case 'AE': // United Arab Emirates
			case 'YE': // Yemen

				$start_of_week = 6; // Saturday

				break;

			default:

				$start_of_week = 1; // Monday

		}

		update_option( 'start_of_week', absint( $start_of_week ) );

	}

}
