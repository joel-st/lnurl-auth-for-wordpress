<?php

namespace JoelMelon\Plugins\LNURLAuth\Plugin;

/**
 * Functions to manage transients used for lnurl auth
 *
 * @author Joel Stüdle <joel.stuedle@gmail.com>
 * @since 1.0.0
 */
class Transients {

	public $transient_prefix     = '';
	public $transient_expiration = '';

	public function __construct() {
		$this->transient_prefix     = 'lnurl-auth-';
		$this->transient_expiration = 300;
	}

	public function exist( $key = '' ) {
		if ( gettype( $key ) !== 'string' || empty( $key ) ) {
			return false;
		}
		return get_transient( $this->transient_prefix . $key ) ? true : false;
	}

	public function set( $key, $signed = false, $user_id = false, $message = false ) {
		// remove expired transients proactively
		delete_expired_transients();

		if ( gettype( $key ) !== 'string' || empty( $key ) ) {
			return false;
		}

		$expiration = $this->transient_expiration;
		$transient  = $this->get( $key );

		if ( ! empty( $transient ) && isset( $transient['time'] ) ) {
			$diff = strtotime( current_time( 'mysql' ) ) - strtotime( $transient['time'] );
			if ( $diff > 0 ) {
				$expiration = $this->transient_expiration - $diff;
			}
		}

		return set_transient(
			$this->transient_prefix . $key,
			array(
				'time'    => current_time( 'mysql' ),
				'signed'  => $signed,
				'user_id' => $user_id,
				'message' => $message,
			),
			$expiration
		); // expires in 5min
	}

	public function get( $key ) {
		if ( gettype( $key ) !== 'string' || empty( $key ) ) {
			return false;
		}
		return get_transient( $this->transient_prefix . $key );
	}

	public function delete( $key ) {
		if ( gettype( $key ) !== 'string' || empty( $key ) ) {
			return false;
		}
		return delete_transient( $this->transient_prefix . $key );
	}
}
