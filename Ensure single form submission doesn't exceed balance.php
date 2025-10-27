<?php
/**
 * Ensure Single Form Submission Doesn't Exceed Balance
 *
 * GOAL:
 * - Prevents a single (non-nested) form submission from requesting more than the available balance
 *   in your ledger. The available balance is calculated as: total in the ledger for this person/entity
 *   minus any of their requests that are still pending in workflow.
 *
 * REQUIREMENTS:
 * - Gravity Forms installed (uses GFAPI, GFCommon).
 * - Optional: Gravity Flow installed (the "pending" filter is only applied when Gravity Flow is active).
 *
 * CONFIGURATION:
 * - Edit the configuration block below (Form Settings). You can add multiple forms.
 *   For each form:
 *     - amount_field_id: The Amount field on the form being submitted.
 *     - name_field_id:   The field that identifies the person/entity (used to match to the ledger entries).
 *     - ledger_form_id:  The ID of the separate "ledger" form holding deposits/credits.
 *     - ledger_name_id:  The field on the ledger form that identifies the person/entity.
 *     - ledger_amount_id:The Amount field on the ledger form to total up.
 *
 * NOTES:
 * - Amounts are parsed using the site's currency format for accuracy.
 */

( static function () {
	// === Form Settings (edit these IDs to match your site) ===
	$form_settings = [
		46 => [
			'amount_field_id'  => 8,
			'name_field_id'    => 19,
			'ledger_form_id'   => 47,
			'ledger_name_id'   => 1,
			'ledger_amount_id' => 4,
		],
		// Add more forms like this:
		// 123 => [ 'amount_field_id' => 1, 'name_field_id' => 2, 'ledger_form_id' => 10, 'ledger_name_id' => 1, 'ledger_amount_id' => 3 ],
	];
	// ============================================

	// Dependency guards: if GFAPI is missing, bail early.
	if ( ! class_exists( 'GFAPI' ) ) {
		return;
	}

	$to_number = static function ( $val ) {
		if ( class_exists( 'GFCommon' ) && method_exists( 'GFCommon', 'to_number' ) ) {
			$currency = GFCommon::get_currency();
			return GFCommon::to_number( (string) $val, $currency );
		}
		return is_numeric( $val ) ? (float) $val : 0.0;
	};

	$collect_submitted_value = static function ( $field_id ) {
		// Try single-input first (e.g., text/number).
		$single = rgpost( 'input_' . $field_id );
		if ( ! is_null( $single ) && '' !== $single ) {
			return is_string( $single ) ? trim( $single ) : $single;
		}
		// Fall back to combining sub-inputs (e.g., Name first/last).
		$parts = [];
		foreach ( $_POST as $key => $val ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( is_string( $key ) && str_starts_with( $key, 'input_' . $field_id . '_' ) ) {
				$trimmed = is_string( $val ) ? trim( $val ) : $val;
				if ( '' !== $trimmed ) {
					$parts[] = $trimmed;
				}
			}
		}
		return implode( ' ', $parts );
	};

	$get_all_entries = static function ( $form_id, array $search_criteria ) {
		$total_count   = 0;
		$paging_offset = 0;
		$paging        = [
			'offset'    => $paging_offset,
			'page_size' => 25,
		];
		$entries       = GFAPI::get_entries( (int) $form_id, $search_criteria, null, $paging, $total_count );
		if ( is_wp_error( $entries ) ) {
			return [];
		}
		while ( $total_count > count( $entries ) ) {
			$paging_offset += 25;
			$paging         = [
				'offset'    => $paging_offset,
				'page_size' => 25,
			];
			$batch          = GFAPI::get_entries( (int) $form_id, $search_criteria, null, $paging, $total_count );
			if ( is_wp_error( $batch ) ) {
				break;
			}
			foreach ( $batch as $e ) {
				$entries[] = $e;
			}
		}
		return $entries;
	};

	$ledger_total_cache = [];

	add_filter(
		'gform_validation',
		static function ( $validation_result ) use ( $form_settings, $to_number, $collect_submitted_value, $get_all_entries, &$ledger_total_cache ) {
			$form = rgar( $validation_result, 'form' );
			if ( empty( $form ) || ! is_array( $form ) ) {
				return $validation_result;
			}

			$form_id = (int) rgar( $form, 'id' );
			if ( ! isset( $form_settings[ $form_id ] ) ) {
				return $validation_result;
			}
			$cfg = $form_settings[ $form_id ];

			$amount_field_id  = (int) $cfg['amount_field_id'];
			$name_field_id    = (int) $cfg['name_field_id'];
			$ledger_form_id   = (int) $cfg['ledger_form_id'];
			$ledger_name_id   = (int) $cfg['ledger_name_id'];
			$ledger_amount_id = (string) $cfg['ledger_amount_id'];

			// Get submitted amount using GF-aware number parsing.
			$raw_amount = rgpost( 'input_' . $amount_field_id );
			$amount     = $to_number( $raw_amount );
			if ( $amount <= 0 ) {
				return $validation_result; // Nothing to validate.
			}

			// Get submitted identifier (e.g., name) to match against the ledger.
			$name_value = $collect_submitted_value( $name_field_id );
			if ( '' === $name_value ) {
				return $validation_result; // Cannot match without an identifier.
			}

			// Build search for ledger entries for this person/entity.
			$ledger_key   = $ledger_form_id . '|' . $ledger_name_id . '|' . md5( $name_value );
			$ledger_total = $ledger_total_cache[ $ledger_key ] ?? null;
			if ( is_null( $ledger_total ) ) {
				$ledger_search  = [
					'status'        => 'active',
					'field_filters' => [
						[
							'key'   => (string) $ledger_name_id,
							'value' => $name_value,
						],
					],
				];
				$ledger_entries = $get_all_entries( $ledger_form_id, $ledger_search );
				$total          = 0.0;
				foreach ( $ledger_entries as $e ) {
					$total += $to_number( rgar( $e, $ledger_amount_id ) );
				}
				$ledger_total_cache[ $ledger_key ] = $total;
				$ledger_total                      = $total;
			}

			// Find pending disbursals on this same form for this person/entity.
			$filters = [
				[
					'key'   => (string) $name_field_id,
					'value' => $name_value,
				],
			];
			if ( function_exists( 'gravity_flow' ) || class_exists( 'Gravity_Flow' ) ) {
				$filters[] = [
					'key'   => 'workflow_final_status',
					'value' => 'pending',
				];
			}
			$pending_search = [
				'status'        => 'active',
				'field_filters' => $filters,
			];
			$child_entries  = $get_all_entries( $form_id, $pending_search );

			$pending_total = 0.0;
			foreach ( $child_entries as $ce ) {
				$pending_total += $to_number( rgar( $ce, (string) $amount_field_id ) );
			}

			$available_balance = $ledger_total - $pending_total;

			if ( $amount > $available_balance ) {
				// Mark the amount field as invalid and show a helpful message.
				$money_balance = number_format( max( 0, $available_balance ), 2 );
				$amount_over   = number_format( $amount - max( 0, $available_balance ), 2 );

				$fields = rgar( $form, 'fields', [] );
				foreach ( $fields as $f ) {
					if ( isset( $f->id ) && (int) $f->id === $amount_field_id ) {
						$f->failed_validation  = true;
						$f->validation_message = 'With this request, your total requested amount exceeds your available balance of $' . $money_balance . ' by $' . $amount_over . '.';
						break;
					}
				}
				$validation_result['is_valid'] = false;
				$validation_result['form']     = $form;
			}

			return $validation_result;
		},
		10
	);
} )();
