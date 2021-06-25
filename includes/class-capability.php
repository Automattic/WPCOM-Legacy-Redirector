<?php
/**
 * Create capability for plugin management.
 *
 * @package WPCOM_Legacy_Redirect
 */

namespace Automattic\LegacyRedirector;

/**
 * Creates role cap and assigns to roles.
 */
final class Capability {
	const MANAGE_REDIRECTS_CAPABILITY = 'manage_redirects';

	// Used to flip the version of the available roles capabilities.
	const CAPABILITIES_VER = 1;

	/**
	 * Add custom capability onto some existing roles using VIP Helpers with fallbacks.
	 */
	public function register() {
		$capabilities_version_key = $this->get_capabilities_version_key();

		// We disable capabilities register unless there is a version increment.
		if ( self::CAPABILITIES_VER <= get_option( $capabilities_version_key, 0 ) ) {
			return false;
		}

		if ( function_exists( 'wpcom_vip_add_role_caps' ) ) {
			wpcom_vip_add_role_caps( 'administrator', self::MANAGE_REDIRECTS_CAPABILITY );
			wpcom_vip_add_role_caps( 'editor', self::MANAGE_REDIRECTS_CAPABILITY );
		} else {
			$roles = array( 'administrator', 'editor' );
			foreach ( $roles as $role ) {
				$role_obj = get_role( $role );
				$role_obj->add_cap( self::MANAGE_REDIRECTS_CAPABILITY );
			}
		}

		update_option( $capabilities_version_key, self::CAPABILITIES_VER );

		return true;
	}

	/**
	 * Unregister the capabilities.
	 *
	 * @return true
	 */
	public function unregister() {
		$capabilities_version_key = $this->get_capabilities_version_key();

		if ( function_exists( 'wpcom_vip_remove_role_caps' ) ) {
			wpcom_vip_remove_role_caps( 'administrator', self::MANAGE_REDIRECTS_CAPABILITY );
			wpcom_vip_remove_role_caps( 'editor', self::MANAGE_REDIRECTS_CAPABILITY );
		} else {
			$roles = array( 'administrator', 'editor' );
			foreach ( $roles as $role ) {
				$role_obj = get_role( $role );
				$role_obj->remove_cap( self::MANAGE_REDIRECTS_CAPABILITY );
			}
		}

		delete_option( $capabilities_version_key, self::CAPABILITIES_VER );

		return true;
	}

	/**
	 * Gets capabilities version key.
	 *
	 * @return string
	 */
	private function get_capabilities_version_key() {
		return self::MANAGE_REDIRECTS_CAPABILITY . '_capability_version';
	}
}
