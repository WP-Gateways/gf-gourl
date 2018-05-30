<?php
/*
Plugin Name: Gravity Forms GoUrl Add-On
Plugin URI:  https://wpgateways.com/products/gourl-bitcoin-atcoin-gateway-gravity-forms/
Description: Integrates Gravity Forms with GoUrl Payments, enabling end users to purchase goods and services through Gravity Forms.
Version: 1.0.0
Author: wpgateways
Author URI: https://wpgateways.com
License: GPL-2.0+
Text Domain: gf-gourl
Domain Path: /languages

------------------------------------------------------------------------
Copyright 2009-2018 wpgateways

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/


define( 'GF_GOURL_VERSION', '1.0.0' );

add_action( 'gform_loaded', array( 'GF_GoUrl_Bootstrap', 'load' ), 5 );

class GF_GoUrl_Bootstrap {

	public static function load() {

		if ( ! method_exists( 'GFForms', 'include_payment_addon_framework' ) ) {
			return;
		}

		require_once( 'class-gf-gourl.php' );

		GFAddOn::register( 'GFGoUrl' );
	}
}

function gf_gourl() {
	return GFGoUrl::get_instance();
}

function gf_gourl_add_cryptocurrencies( $currencies ) {
	global $gourl;
	foreach( $gourl->payments() as $code => $name ) {
		if( !isset( $currencies[$code] ) ) {
			$currencies[$code] = array(
				'name'               => $name,
				'symbol_left'        => $code,
				'symbol_right'       => '',
				'symbol_padding'     => ' ',
				'thousand_separator' => ',',
				'decimal_separator'  => '.',
				'decimals'           => 8
			);
		}
	}
	return $currencies;
}
add_filter( 'gform_currencies', 'gf_gourl_add_cryptocurrencies', 100 );

function wpgw_gravityforms_gourlcallback( $user_id = 0, $entry_id = '', $payment_details = array(), $box_status = '' ) {
    //wp_mail( 'mohsinoffline@gmail.com', 'callback ' . $entry_id, 'status: ' . $box_status . print_r( $payment_details, 1 ) );
	gf_gourl()->init_callback( $user_id, $entry_id, $payment_details, $box_status );
}
