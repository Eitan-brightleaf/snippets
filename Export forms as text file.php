<?php
/**
 * Export Forms as Text File (Admin Tool)
 *
 * GOAL:
 * - Adds an option under Forms > Import/Export to download selected forms as a human‑readable .txt file
 *   including fields, confirmations, notifications, and (optionally) Gravity Flow notification steps.
 *
 * REQUIREMENTS:
 * - Gravity Forms core (admin access with gravityforms_edit_forms capability).
 * - Optional: Gravity Flow for exporting workflow notifications.
 */

( static function () {
	if ( ! class_exists( 'GFExport' ) || ! class_exists( 'GFCommon' ) || ! class_exists( 'RGFormsModel' ) ) {
		return;
	}

	// Render custom export page content.
	add_action(
		'gform_export_page_export_forms_as_text',
		static function () {
			if ( true !== GFCommon::current_user_can_any( 'gravityforms_edit_forms' ) ) {
				wp_die( 'You do not have permission to access this page' );
			}
			GFExport::page_header();
			?>
			<script type="text/javascript">
			(function( $ ){
				$( document ).on( 'click keypress', '#gf_export_forms_all', function( e ) {
                    const checked = e.target.checked,
                        label = $('label[for="gf_export_forms_all"]'),
                        formList = $('#export_form_list');
                    label.find( 'strong' ).html( checked ? label.data( 'deselect' ) : label.data( 'select' ) );
					$( 'input[name]', formList ).prop( 'checked', checked );
				} );
			}( jQuery ));
			</script>
			<div class="gform-settings__content">
				<form method="post" id="gform_export_as_text" class="gform_settings_form">
					<?php wp_nonce_field( 'gf_export_forms_as_text', 'gf_export_forms_as_text_nonce' ); ?>
					<div class="gform-settings-panel gform-settings-panel--full">
						<header class="gform-settings-panel__header"><legend class="gform-settings-panel__title">Export Forms as Text</legend></header>
						<div class="gform-settings-panel__content">
							<div class="gform-settings-description">
								Select the forms you would like to export. When you click the download button below, a text file will be generated for you to save to your computer.
							</div>
							<table class="form-table">
								<tr style="vertical-align:top;">
									<th scope="row">
										<label for="export_fields">Select Forms</label> <?php gform_tooltip( 'export_select_forms' ); ?>
									</th>
									<td>
										<ul id="export_form_list">
											<li>
												<input type="checkbox" id="gf_export_forms_all" />
												<label for="gf_export_forms_all" data-deselect="Deselect All" data-select="Select All">Select All</label>
											</li>
											<?php
											$forms = RGFormsModel::get_forms();
											foreach ( $forms as $form ) {
												?>
												<li>
													<input type="checkbox" name="gf_form_id[]" id="gf_form_id_<?php echo absint( $form->id ); ?>" value="<?php echo absint( $form->id ); ?>" />
													<label for="gf_form_id_<?php echo absint( $form->id ); ?>"><?php echo esc_html( $form->title ); ?></label>
												</li>
												<?php
											}
											?>
										</ul>
									</td>
								</tr>
							</table>
							<br /><br />
							<input type="submit" value="Download Export File" name="export_forms_as_text" class="button large primary" />
						</div>
					</div>
				</form>
			</div>
			<?php
			GFExport::page_footer();
		}
	);

	// Export runner closure.
	$do_export = static function ( $form_ids ) {
		if ( empty( $form_ids ) || ! is_array( $form_ids ) ) {
			return;
		}
		$forms = RGFormsModel::get_form_meta_by_id( $form_ids );
		if ( empty( $forms ) || ! is_array( $forms ) ) {
			GFCommon::add_error_message( 'No forms found to export.' );
			return;
		}

		$forms_text_array = [];
		foreach ( $forms as $form ) {
			$form_title                                       = isset( $form['title'] ) ? (string) $form['title'] : 'Untitled Form';
			$forms_text_array[ $form_title ]                  = [];
			$forms_text_array[ $form_title ]['fields']        = [];
			$forms_text_array[ $form_title ]['confirmations'] = [];
			$forms_text_array[ $form_title ]['notifications'] = [];
			$forms_text_array[ $form_title ]['workflow_notifications'] = [];

			if ( isset( $form['fields'] ) && is_array( $form['fields'] ) ) {
				foreach ( $form['fields'] as $field ) {
					$label                                       = is_object( $field ) && isset( $field->label ) ? (string) $field->label : ( isset( $field['label'] ) ? (string) $field['label'] : '' );
					$forms_text_array[ $form_title ]['fields'][] = $label;
				}
			}

			if ( isset( $form['confirmations'] ) && is_array( $form['confirmations'] ) ) {
				foreach ( $form['confirmations'] as $confirmation ) {
					$name    = isset( $confirmation['name'] ) ? (string) $confirmation['name'] : '';
					$message = isset( $confirmation['message'] ) ? wp_strip_all_tags( (string) $confirmation['message'] ) : '';
					$forms_text_array[ $form_title ]['confirmations'][] = $name . PHP_EOL . $message;
				}
			}

			if ( isset( $form['notifications'] ) && is_array( $form['notifications'] ) ) {
				foreach ( $form['notifications'] as $notification ) {
					$is_active = ! empty( $notification['isActive'] );
					$forms_text_array[ $form_title ]['notifications'][] =
						( isset( $notification['name'] ) ? 'Name: ' . $notification['name'] . PHP_EOL : '' ) .
						( isset( $notification['toEmail'] ) ? 'To: ' . $notification['toEmail'] . PHP_EOL : '' ) .
						( isset( $notification['event'] ) ? 'Event: ' . $notification['event'] . PHP_EOL : '' ) .
						( isset( $notification['subject'] ) ? 'Subject: ' . $notification['subject'] . PHP_EOL : '' ) .
						( isset( $notification['message'] ) ? 'Message: ' . wp_strip_all_tags( (string) $notification['message'] ) . PHP_EOL : '' ) .
						'Is Active: ' . ( $is_active ? 'true' : 'false' ) . PHP_EOL;
				}
			}

			if ( function_exists( 'gravity_flow' ) && is_object( gravity_flow() ) ) {
				$steps = gravity_flow()->get_steps( $form['id'] );
				foreach ( (array) $steps as $step ) {
					if ( is_object( $step ) && method_exists( $step, 'get_type' ) && 'notification' === $step->get_type() ) {
						$forms_text_array[ $form_title ]['workflow_notifications'][] =
							'Step name: ' . $step->__get( 'step_name' ) . PHP_EOL .
							'Subject: ' . $step->__get( 'workflow_notification_subject' ) . PHP_EOL .
							'Message: ' . wp_strip_all_tags( (string) $step->__get( 'workflow_notification_message' ) ) . PHP_EOL;
					}
				}
			}
		}

		$filename = 'gravityforms_export_as_text_' . ( function_exists( 'wp_date' ) ? wp_date( 'Y-m-d' ) : date( 'Y-m-d' ) ) . '.txt';
		$filename = sanitize_file_name( $filename );
		header( 'Content-Description: File Transfer' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Content-Type: text/plain; charset=' . get_option( 'blog_charset' ) );

		$forms_text = '';
		foreach ( $forms_text_array as $form_name => $form_data ) {
			$forms_text .= $form_name . PHP_EOL;
			foreach ( $form_data as $section => $items ) {
				$forms_text .= $section . PHP_EOL;
				$idx         = 1;
				foreach ( (array) $items as $item ) {
					$forms_text .= $idx . ': ' . $item . PHP_EOL;
					$idx++;
				}
				$forms_text .= PHP_EOL;
			}
			$forms_text .= PHP_EOL;
		}
		echo $forms_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	};

	// Handle form submission (download file).
	add_action(
		'gform_loaded',
		static function () use ( $do_export ) {
			if ( isset( $_POST['export_forms_as_text'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				check_admin_referer( 'gf_export_forms_as_text', 'gf_export_forms_as_text_nonce' );
				$selected_forms = isset( $_POST['gf_form_id'] ) ? (array) $_POST['gf_form_id'] : [];
				if ( empty( $selected_forms ) ) {
					GFCommon::add_error_message( 'Please select the forms to be exported' );
					return;
				}
				$selected_forms = array_map( 'absint', $selected_forms );
				$do_export( $selected_forms );
			}
		}
	);

	// Register menu tab in Export screen.
	add_filter(
		'gform_export_menu',
		static function ( $setting_tabs ) {
			if ( true === GFCommon::current_user_can_any( 'gravityforms_edit_forms' ) ) {
				$icon               = '<svg width="24" height="24" role="presentation" focusable="false"  viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><title>export forms as text</title><g class="nc-icon-wrapper"><path d="M5 3.75h14c.69 0 1.25.56 1.25 1.25v14c0 .69-.56 1.25-1.25 1.25H5c-.69 0-1.25-.56-1.25-1.25V5c0-.69.56-1.25 1.25-1.25z" stroke="#111111" stroke-width="1.5"/><path d="M9 4L5 8.5V4h4z" fill="#111111" stroke="#111111"/><path d="M15.286 11L12 8l-3 3" stroke="#111111" stroke-width="1.5"/><path fill="#111111" d="M11 9h2v8h-2z"/></g></svg>';
				$setting_tabs['40'] = [
					'name'  => 'export_forms_as_text',
					'label' => 'Export Forms As Text',
					'icon'  => $icon,
				];
			}
			return $setting_tabs;
		}
	);
} )();
