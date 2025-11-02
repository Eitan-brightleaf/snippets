<?php
/**
 * Rename GravityView "Workflow Complete" to the Actual Step Name
 *
 * GOAL:
 * - When GravityView displays the status "Workflow Complete", replace it with the configured
 *   Workflow Complete step name from Gravity Flow for selected forms.
 *
 *
 * CONFIGURATION:
 * - Update $form_ids below to the list of form IDs this should apply to.
 *
 * NOTES:
 * - Runs only when the field value is exactly "Workflow Complete".
 */

( static function () {
    // === Configure applicable form IDs =========================================
    $form_ids = [ 3, 45, 5, 7, 21, 51, 25, 11, 29 ];
    // ==========================================================================

    add_filter(
            'gravityview_field_output',
            static function ( $html, $args ) use ( $form_ids ) {
                $form_id = ( isset( $args['entry']['form_id'] ) ) ? (int) $args['entry']['form_id'] : 0;
                if ( 0 === $form_id || true !== in_array( $form_id, $form_ids, true ) ) {
                    return $html;
                }
                $value = isset( $args['value'] ) ? (string) $args['value'] : '';
                if ( 'Workflow Complete' !== $value ) {
                    return $html;
                }
                if ( ! class_exists( 'Gravity_Flow' ) || ! function_exists( 'gravity_flow' ) ) {
                    return $html;
                }
                $gf = Gravity_Flow::get_instance();
                if ( ! is_object( $gf ) ) {
                    return $html;
                }
                $entry = $args['entry'] ?? null;
                $step  = $gf->get_workflow_complete_step( $form_id, $entry );
                if ( ! is_object( $step ) || ! method_exists( $step, 'get_name' ) ) {
                    return $html;
                }
                $step_name = (string) $step->get_name();
                if ( '' === $step_name ) {
                    return $html;
                }
                // Replace exact phrase in the rendered HTML.
                return str_replace( 'Workflow Complete', $step_name, $html );
            },
            10,
            2
    );
} )();
