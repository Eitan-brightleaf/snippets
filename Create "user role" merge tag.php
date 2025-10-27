<?php
/**
 * Create User Role Merge Tags ({user_role}, {user_primary_role})
 *
 * GOAL:
 * - Provides {user_role} (all roles) and {user_primary_role} merge tags for Gravity Forms.
 * - Supports modifiers: :separator(|) to change joiner and :display to output role display names.
 *
 * REQUIREMENTS:
 * - Gravity Forms core is required.
 *
 * CONFIGURATION:
 * - Adjust the defaults in the $cfg block below if desired.
 *   - separator: string used to join roles for {user_role}.
 *   - logged_out_label: what to output for logged-out users (empty string by default).
 *   - prefer_display_names: whether to default to display names instead of slugs (can be overridden per-tag using :display).
 *
 * NOTES:
 * - Works in notifications, confirmations, field labels, default values, etc.
 */

( static function () {
	if ( ! function_exists( 'wp_get_current_user' ) ) {
		return;
	}

	// === Defaults (edit these) =================================================
	$cfg = [
		'separator'            => ', ',
		'logged_out_label'     => '',
		'prefer_display_names' => false,
	];
	// ==========================================================================

	// Cache of role display names for this request.
	$cache = [ 'display_map' => null ];

	$roles_display_map = static function () use ( &$cache ) {
		if ( null !== $cache['display_map'] ) {
			return $cache['display_map'];
		}
		$map = [];
		if ( function_exists( 'wp_roles' ) ) {
			$roles_obj = wp_roles();
			if ( is_object( $roles_obj ) && isset( $roles_obj->roles ) && is_array( $roles_obj->roles ) ) {
				foreach ( $roles_obj->roles as $slug => $data ) {
					$map[ $slug ] = isset( $data['name'] ) ? (string) $data['name'] : (string) $slug;
				}
			}
		}
		$cache['display_map'] = $map;
		return $map;
	};

	add_filter(
		'gform_custom_merge_tags',
		static function ( $merge_tags ) {
			$merge_tags[] = [
				'label' => 'User Role(s)',
				'tag'   => '{user_role}',
			];
			$merge_tags[] = [
				'label' => 'Primary User Role',
				'tag'   => '{user_primary_role}',
			];
			return $merge_tags;
		}
	);

	add_filter(
		'gform_replace_merge_tags',
		static function ( $text, $form = null, $entry = null, $url_encode = false, $esc_html = false ) use ( $cfg, $roles_display_map ) {
			// Quick check to skip early.
			if ( false === is_string( $text ) || ( false === strpos( $text, '{user_role' ) && false === strpos( $text, '{user_primary_role' ) ) ) {
				return $text;
			}

			$user        = wp_get_current_user();
			$uid         = (int) ( $user->ID ?? 0 );
			$roles_slugs = ( 0 !== $uid && isset( $user->roles ) && is_array( $user->roles ) ) ? array_values( $user->roles ) : [];

			$display_map    = $roles_display_map();
			$default_sep    = (string) $cfg['separator'];
			$logged_out     = (string) $cfg['logged_out_label'];
			$prefer_display = (bool) $cfg['prefer_display_names'];

			$replace_cb = static function ( $matches ) use ( $roles_slugs, $display_map, $default_sep, $logged_out, $prefer_display ) {
				$tag      = $matches[1]; // 'user_role' or 'user_primary_role'
				$mods_str = $matches[2] ?? '';
				$mods     = [];
				if ( '' !== $mods_str ) {
					foreach ( explode( ':', trim( $mods_str, ':' ) ) as $m ) {
						if ( '' !== $m ) {
							$mods[] = $m;
						}
					}
				}

				$use_display = $prefer_display;
				$sep         = $default_sep;
				foreach ( $mods as $m ) {
					if ( 'display' === $m ) {
						$use_display = true;
					} elseif ( 0 === strpos( $m, 'separator(' ) && ')' === substr( $m, -1 ) ) {
						$inside = substr( $m, 10, -1 );
						$sep    = (string) $inside;
					}
				}

				if ( empty( $roles_slugs ) ) {
					return $logged_out;
				}

				if ( 'user_primary_role' === $tag ) {
					$primary = (string) $roles_slugs[0];
					return true === $use_display && isset( $display_map[ $primary ] ) ? (string) $display_map[ $primary ] : $primary;
				}

				// user_role (all roles)
				$roles_out = [];
				foreach ( $roles_slugs as $slug ) {
					$roles_out[] = ( true === $use_display && isset( $display_map[ $slug ] ) ) ? (string) $display_map[ $slug ] : (string) $slug;
				}
				return implode( $sep, $roles_out );
			};

			// Replace occurrences for both tags with optional modifiers.
			$pattern = '/\{(user_role|user_primary_role)((?::[^\}]+)?)\}/';
			$out     = preg_replace_callback( $pattern, $replace_cb, $text );

			// Respect esc/url encode flags.
			if ( true === $esc_html ) {
				$out = esc_html( $out );
			}
			if ( true === $url_encode ) {
				$out = rawurlencode( $out );
			}

			return $out;
		},
		10,
		5
	);
} )();
