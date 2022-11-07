<?php
/**
 * API Class
 *
 * @package    WordPress
 * @author     Javier Casares <javier@casares.org>, David Perez <david@closemarketing.es>
 * @version    1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Gets Green Check from The Green Web Foundation API
 *
 * @param string $hostname HostName
 * @return array
 */
function wpsustainable_get_greencheck( $hostname ) {

	$args = array(
		'timeout'   => 3000,
		'sslverify' => false,
	);
	$key = 'wpsustainable_tgwf';
	$wpsustainable = get_transient( $key );
	if ( ! $wpsustainable ) {
		$url = 'https://admin.thegreenwebfoundation.org/api/v3/greencheck/' . $hostname;
		$response = wp_remote_get( $url, $args );
		if ( isset( $response['response']['code'] ) && 200 === $response['response']['code'] ) {
			$body = wp_remote_retrieve_body( $response );
			set_transient( $key, $body, HOUR_IN_SECONDS * 24 );
		}
	}
	return json_decode( $wpsustainable, true );
}

/**
 * Gets CO2 Intensity from The Green Web Foundation API
 *
 * @param string $hostname HostName
 * @return array
 */
function wpsustainable_get_co2intensity( $hostname ) {

	$args = array(
		'timeout'   => 3000,
		'sslverify' => false,
	);
	$key = 'wpsustainable_co2i';
	$wpsustainable = get_transient( $key );
	if ( ! $wpsustainable ) {

		$hostip = gethostbynamel( $hostname );
		if( $hostip ) {
			$url = 'https://admin.thegreenwebfoundation.org/api/v3/ip-to-co2intensity/' . $hostip;
			$response = wp_remote_get( $url, $args );
			if ( isset( $response['response']['code'] ) && 200 === $response['response']['code'] ) {
				$body = wp_remote_retrieve_body( $response );
				set_transient( $key, $body, HOUR_IN_SECONDS * 24 );
			}
		} else {
			$wpsustainable = false;
		}
	}
	return json_decode( $wpsustainable, true );
}

/**
 * Get vulnerabilities from Plugin
 *
 * @param string $slug Slug of plugin.
 * @param string $version Version of plugin.
 * @return array
 */
function wpsustainable_get( $hostname ) {
	
	$wpsustainable = array(
		'green' => array(
			'url' => null,
			'hosting' => null,
			'hosting_url' => null,
			'green' => null,
			'docs' => array()
		),
		'co2intensity' => array(
			'country' => null,
			'country_iso_2' => null,
			'country_iso_3' => null, 
			'intensity' => null,
			'intensity_type' => null,
			'fossil' => null,
			'year' => null,
			'ip' => null
		)
	);

	$response_greencheck = wpsustainable_get_greencheck( $hostname );

	if ( isset( $response_greencheck['data'] ) && !$response_greencheck['data'] ) {
		$wpsustainable['green'] = false;
	} elseif ( isset( $response_greencheck['modified'] ) && $response_greencheck['modified'] ) {

		if( isset( $response_greencheck['url'] ) && $response_greencheck['url'] ) {
			$wpsustainable['green']['url'] = wp_filter_nohtml_kses( $response_greencheck['url'] );
		}

		if( isset( $response_greencheck['hosted_by'] ) && $response_greencheck['hosted_by'] ) {
			$wpsustainable['green']['hosting'] = wp_filter_nohtml_kses( $response_greencheck['hosted_by'] );
		}

		if( isset( $response_greencheck['hosted_by_website'] ) && $response_greencheck['hosted_by_website'] ) {
			$wpsustainable['green']['hosting_url'] = wp_filter_nohtml_kses( $response_greencheck['hosted_by_website'] );
		}

		if( isset( $response_greencheck['green'] ) && $response_greencheck['green'] == true ) {
			$wpsustainable['green']['is_green'] = true;
		} elseif( isset( $response_greencheck['green'] ) && $response_greencheck['green'] == false ) {
			$wpsustainable['green']['is_green'] = false;
		}

		if( isset( $response_greencheck['modified'] ) && $response_greencheck['modified'] ) {
			$wpsustainable['green']['modified'] = wp_filter_nohtml_kses( $response_greencheck['modified'] );
		}

		if( isset( $response_greencheck['supporting_documents'] ) && is_array( $response_greencheck['supporting_documents'] ) ) {
			foreach( $response_greencheck['supporting_documents'] as $supporting_documents ) {
				$wpsustainable['green']['docs'][] = array(
					'name' => wp_filter_nohtml_kses( $response_greencheck['title'] ),
					'url' => wp_filter_nohtml_kses( $response_greencheck['link'] )
				);
				unset( $supporting_documents );
			}
		}

	}

	unset( $response_greencheck );

	$response_co2intensity = wpsustainable_get_co2intensity( $hostname );

	if ( isset( $response_co2intensity ) ) {

		if( isset( $response_greencheck['country_name'] ) && $response_greencheck['country_name'] ) {
			$wpsustainable['co2intensity']['country'] = wp_filter_nohtml_kses( $response_greencheck['country_name'] );
			$wpsustainable['co2intensity']['country_iso_2'] = strtoupper( wp_filter_nohtml_kses( $response_greencheck['country_code_iso_2'] ) );
			$wpsustainable['co2intensity']['country_iso_3'] = strtoupper( wp_filter_nohtml_kses( $response_greencheck['country_code_iso_3'] ) );
		}

		if( isset( $response_greencheck['carbon_intensity_type'] ) && $response_greencheck['carbon_intensity_type'] && isset( $response_greencheck['carbon_intensity'] ) && $response_greencheck['carbon_intensity'] ) {
			$wpsustainable['co2intensity']['intensity_type'] = wp_filter_nohtml_kses( $response_greencheck['carbon_intensity_type'] );
			$wpsustainable['co2intensity']['intensity'] = number_format( (float) wp_filter_nohtml_kses( $response_greencheck['carbon_intensity'] ), 3, '.', '' );
		}

		if( isset( $response_greencheck['generation_from_fossil'] ) && $response_greencheck['generation_from_fossil'] ) {
			$wpsustainable['co2intensity']['fossil'] = number_format( (float) wp_filter_nohtml_kses( $response_greencheck['generation_from_fossil'] ), 2, '.', '' ) . ' %';
		}
		
		if( isset( $response_greencheck['year'] ) && $response_greencheck['year'] ) {
			$wpsustainable['co2intensity']['year'] = wp_filter_nohtml_kses( $response_greencheck['year'] );
		}
		
		if( isset( $response_greencheck['checked_ip'] ) && $response_greencheck['checked_ip'] ) {
			$wpsustainable['co2intensity']['ip'] = wp_filter_nohtml_kses( $response_greencheck['ip'] );
		}

	}

	unset( $response_co2intensity );
	
	return $wpsustainable;
}
