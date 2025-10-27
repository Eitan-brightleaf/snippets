<?php
add_filter(
	'gravityflow_step_settings_fields',
	function ( $settings, $step ) {

		// Normalize to a step object and determine type in a locale-safe way.
		$flow                = null;
		$type                = null;
		$has_user_input_step = false;

		if ( function_exists( 'gravity_flow' ) ) {
			$flow = gravity_flow();
		}

		if ( $flow && is_object( $flow ) && method_exists( $flow, 'get_step' ) ) {
			if ( ! ( is_object( $step ) && method_exists( $step, 'get_type' ) ) ) {
				$step = $flow->get_step( $step );
			}
			if ( is_object( $step ) && method_exists( $step, 'get_type' ) ) {
				$type = $step->get_type();

				if ( method_exists( $step, 'get_form_id' ) && method_exists( $flow, 'get_steps' ) ) {
					$form_id = $step->get_form_id();
					$steps   = $flow->get_steps( $form_id );
					if ( is_array( $steps ) ) {
						foreach ( $steps as $_s ) {
							if ( is_object( $_s ) && method_exists( $_s, 'get_type' ) && $_s->get_type() === 'user_input' ) {
								$has_user_input_step = true;
								break;
							}
						}
					}
				}
			}
		}

		// Fallback to English titles only if type could not be determined (keeps previous behavior where possible).
		$is_approval   = ( 'approval' === $type );
		$is_user_input = ( 'user_input' === $type );
		if ( ! $is_approval && ! $is_user_input ) {
			$title = rgars( $settings, '1/title' );
			if ( 'Approval' === $title ) {
				$is_approval = true;
			} elseif ( 'User Input' === $title ) {
				$is_user_input = true;
			}
		}

		// Locate the section where the 'expiration' field lives; fallback to the first section with fields.
		$target_section_index = null;
		if ( is_array( $settings ) ) {
			foreach ( $settings as $i => $section ) {
				if ( ! isset( $section['fields'] ) || ! is_array( $section['fields'] ) ) {
					continue;
				}
				$names = array_column( $section['fields'], 'name' );
				if ( in_array( 'expiration', $names, true ) ) {
					$target_section_index = $i;
					break;
				}
				if ( is_null( $target_section_index ) ) {
					$target_section_index = $i; // first section with fields as a reasonable fallback
				}
			}
		}

		if ( is_null( $target_section_index ) ) {
			return $settings;
		}

		$fields = isset( $settings[ $target_section_index ]['fields'] ) && is_array( $settings[ $target_section_index ]['fields'] ) ? $settings[ $target_section_index ]['fields'] : [];
		$names  = array_column( $fields, 'name' );
		$anchor = array_search( 'expiration', $names, true );
		if ( false === $anchor ) {
			$anchor = count( $fields );
		}
		$insert_at = $anchor + 1;

		// Approval step: add fields for custom Approve/Reject button labels and conditionally Revert.
		if ( $is_approval ) {
			$approval_text = [
				'name'    => 'custom_approval_text',
				'label'   => 'Custom Approval Button Text',
				'type'    => 'text',
				'class'   => 'merge-tag-support mt-position-right',
				'tooltip' => 'Enter text that you would like to display instead of "Approve" for this step.',
			];
			array_splice( $fields, $insert_at, 0, [ $approval_text ] );
			$insert_at++;

			$rejection_text = [
				'name'    => 'custom_rejection_text',
				'label'   => 'Custom Rejection Button Text',
				'type'    => 'text',
				'class'   => 'merge-tag-support mt-position-right',
				'tooltip' => 'Enter text that you would like to display instead of "Reject" for this step.',
			];
			array_splice( $fields, $insert_at, 0, [ $rejection_text ] );

			// Only show the Revert label field on Approval steps when the workflow has a User Input step.
			if ( $has_user_input_step ) {
				$insert_at++;
				$revert_text = [
					'name'    => 'custom_revert_text',
					'label'   => 'Custom Revert Button Text',
					'type'    => 'text',
					'class'   => 'merge-tag-support mt-position-right',
					'tooltip' => 'Enter text that you would like to display instead of "Revert" when sending back to a User Input step.',
				];
				array_splice( $fields, $insert_at, 0, [ $revert_text ] );
			}

			$settings[ $target_section_index ]['fields'] = $fields;
		}

		// User Input step: add fields for custom Submit/Update/Save Progress button labels.
		if ( $is_user_input ) {
			$submit_text = [
				'name'    => 'custom_user_input_submit_text',
				'label'   => 'Custom Submit Form Button Text',
				'type'    => 'text',
				'class'   => 'merge-tag-support mt-position-right',
				'tooltip' => 'Enter text that you would like to display instead of "Submit" for this User Input step.',
			];
			array_splice( $fields, $insert_at, 0, [ $submit_text ] );
			$insert_at++;

			$update_text = [
				'name'    => 'custom_user_input_update_text',
				'label'   => 'Custom Update Button Text',
				'type'    => 'text',
				'class'   => 'merge-tag-support mt-position-right',
				'tooltip' => 'Enter text that you would like to display instead of "Update" for this User Input step.',
			];
			array_splice( $fields, $insert_at, 0, [ $update_text ] );
			$insert_at++;

			$save_progress_text = [
				'name'    => 'custom_user_input_save_text',
				'label'   => 'Custom Save Progress Button Text',
				'type'    => 'text',
				'class'   => 'merge-tag-support mt-position-right',
				'tooltip' => 'Enter text to display instead of "Save Progress" for this User Input step.',
			];
			array_splice( $fields, $insert_at, 0, [ $save_progress_text ] );

			$settings[ $target_section_index ]['fields'] = $fields;
		}

		return $settings;
	},
	10,
	2
);

add_filter(
	'gravityflow_approve_label_workflow_detail',
	function ( $approve_label, $step ) {
		return empty( $step->__get( 'custom_approval_text' ) ) ? $approve_label : $step->__get( 'custom_approval_text' );
	},
	10,
	2
);

add_filter(
	'gravityflow_reject_label_workflow_detail',
	function ( $reject_label, $step ) {
		return empty( $step->__get( 'custom_rejection_text' ) ) ? $reject_label : $step->__get( 'custom_rejection_text' );
	},
	10,
	2
);

add_filter(
	'gravityflow_revert_label_workflow_detail',
	function ( $revert_label, $step ) {
		return empty( $step->__get( 'custom_revert_text' ) ) ? $revert_label : $step->__get( 'custom_revert_text' );
	},
	10,
	2
);

// User Input labels: override Submit/Update button texts on workflow detail based on custom step setting.
add_filter(
	'gravityflow_submit_button_text_user_input',
	function ( $label, $form, $step ) {
		$custom = is_object( $step ) && method_exists( $step, '__get' ) ? $step->__get( 'custom_user_input_submit_text' ) : '';
		return empty( $custom ) ? $label : $custom;
	},
	10,
	3
);

add_filter(
	'gravityflow_update_button_text_user_input',
	function ( $label, $form, $step ) {
		$custom = is_object( $step ) && method_exists( $step, '__get' ) ? $step->__get( 'custom_user_input_update_text' ) : '';
		return empty( $custom ) ? $label : $custom;
	},
	10,
	3
);

add_filter(
	'gravityflow_save_progress_button_text_user_input',
	function ( $label, $form, $step ) {
		$custom = is_object( $step ) && method_exists( $step, '__get' ) ? $step->__get( 'custom_user_input_save_text' ) : '';
		return empty( $custom ) ? $label : $custom;
	},
	10,
	3
);
