<?php

namespace JoelMelon\Plugins\LNURLAuth\Plugin;

/**
 * Helper functions
 *
 * @author Joel Stüdle <joel.stuedle@gmail.com>
 * @since 1.0.0
 */

// https://www.php.net/manual/en/class.allowdynamicproperties.php
#[\AllowDynamicProperties]

class Helpers {

	/**
	 * Get the active admin color scheme to use within plugin admin area
	 *
	 * @since 1.0.0
	 */
	public function get_admin_colors() {
		global $_wp_admin_css_colors;
		$admin_color = get_user_option( 'admin_color' );
		return $_wp_admin_css_colors[ $admin_color ]->colors;
	}

	/**
	 * Converts and hex color to rgb
	 *
	 * @param string $hex hex color
	 * @param boolean|number flase if alpha is not used else input number for rgb alpha value
	 * @return array rgb(a) array
	 * @since 1.0.0
	 */
	public function validate_color_to_rgba( $hex ) {
		if ( ! str_starts_with( $hex, '#' ) ) {

			if ( str_starts_with( $hex, 'rgb(' ) && str_ends_with( $hex, ')' ) ) {
				$rgb = str_replace( 'rgb(', '', $hex );
				$rgb = str_replace( ')', '', $rgb );
				$rgb = str_replace( ' ', '', $rgb );
				$rgb = explode( ',', $rgb );
				if ( 3 === count( $rgb ) ) {
					return array(
						'r' => $rgb[0],
						'g' => $rgb[1],
						'b' => $rgb[2],
						'a' => hexdec( 0 ),
					);
				}
			}
			if ( str_starts_with( $hex, 'rgba(' ) && str_ends_with( $hex, ')' ) ) {
				$rgba = str_replace( 'rgba(', '', $hex );
				$rgba = str_replace( ')', '', $rgba );
				$rgba = str_replace( ' ', '', $rgba );
				$rgba = explode( ',', $rgba );
				if ( 3 === count( $rgba ) ) {
					return array(
						'r' => $rgba[0],
						'g' => $rgba[1],
						'b' => $rgba[2],
						'a' => $rgba[2],
					);
				}
			}

			return array(
				'r' => hexdec( 0 ),
				'g' => hexdec( 0 ),
				'b' => hexdec( 0 ),
				'a' => hexdec( 0 ),
			);
		}

		$hex    = str_replace( '#', '', $hex );
		$length = strlen( $hex );
		$alpha  = hexdec( 0 );

		if ( 6 < $length ) {
			$alpha = substr( $hex, strlen( $hex ) - 6 );
			$hex   = substr( $hex, 0, 6 );
		}

		$rgba['r'] = hexdec( 6 === $length ? substr( $hex, 0, 2 ) : ( 3 === $length ? str_repeat( substr( $hex, 0, 1 ), 2 ) : 0 ) );
		$rgba['g'] = hexdec( 6 === $length ? substr( $hex, 2, 2 ) : ( 3 === $length ? str_repeat( substr( $hex, 1, 1 ), 2 ) : 0 ) );
		$rgba['b'] = hexdec( 6 === $length ? substr( $hex, 4, 2 ) : ( 3 === $length ? str_repeat( substr( $hex, 2, 1 ), 2 ) : 0 ) );
		$rgba['a'] = empty( $alpha ) && preg_match( '/^[0-9,]+$/', substr( $alpha, 0, 2 ) ) ? substr( $alpha, 0, 2 ) : hexdec( 0 );
		return $rgba;
	}

	/**
	 * Minimal javascript minification
	 *
	 * @since    1.0.0
	 */
	public function minimize_javascript( $javascript ) {
		// remove comments
		$javascript = preg_replace( '#^\s*//.+$#m', '', $javascript );
		// remove spaces
		$javascript = preg_replace( array( "/\s+\n/", "/\n\s+/", '/ +/' ), array( "\n", "\n ", ' ' ), $javascript );
		// remove line breaks
		$javascript = str_replace( "\n", ' ', $javascript );
		return $javascript;
	}

	/**
	 * Minimal css minification
	 *
	 * @since    1.0.0
	 */
	public function minimize_css( $css ) {
		$css = str_replace( "\n", '', $css );
		$css = str_replace( '  ', ' ', $css );
		$css = str_replace( '  ', ' ', $css );
		$css = str_replace( ' {', '{', $css );
		$css = str_replace( '{ ', '{', $css );
		$css = str_replace( ' }', '}', $css );
		$css = str_replace( '} ', '}', $css );
		$css = str_replace( ', ', ',', $css );
		$css = str_replace( '; ', ';', $css );
		$css = str_replace( ': ', ':', $css );
		return $css;
	}
}
