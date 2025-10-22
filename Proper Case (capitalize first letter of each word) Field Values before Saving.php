<?php
/**
 * Proper Case (Capitalize First Letter of Each Word) Field Values Before Saving
 *
 * GOAL:
 * Automatically converts field values to proper/title case (first letter of each word capitalized)
 * before saving entries. Uses ucwords() PHP function to transform text like "john doe" to "John Doe".
 * Configurable for specific forms and fields.
 *
 * CONFIGURATION REQUIRED:
 * - $form_and_field_ids: change the key to form ID and sub-array values to field IDs
 * - Example: 3 => [5,8,12] - this will apply to form 3, fields 5, 8, and 12
 * - Add more form/field combinations by duplicating the pattern
 * - Example: 3 => [5,8,12], 7 => [2,4]
 * - This will apply to text fields, textarea fields, and any field that stores string values
 * - Note: Consider if email fields, URL fields, or other special fields are in your list
 * - Note: for more complex transformations, see our other snippet: https://brightleafdigital.io/code/entry/31-capitalize-specific-field-value-before-saving-entry/
 */

add_filter(
        'gform_save_field_value',
        function ( $value, $entry, $field, $form ) {
            $form_and_field_ids = [
                    0 => [ 0, 0 ],
            ];
            if ( in_array( intval( $form['id'] ), array_map( 'intval', array_keys( $form_and_field_ids ) ), true )
                 && in_array( intval( $field['id'] ), array_map( 'intval', $form_and_field_ids[ intval( $form['id'] ) ] ), true ) ) {
                $value = ucwords( sanitize_text_field( $value ) );
            }
            return $value;
        },
        10,
        4
);
