<?php
/**
 * Capitalize Specific Text Field Value Before Saving Entry (or perform other transformations)
 *
 * GOAL:
 * Transforms text field values using various string manipulation methods before saving a Gravity Forms entry.
 * By default, converts text to uppercase.
 * Available transformations include:
 * - Case transformations: uppercase, lowercase, title case, camelCase, PascalCase, sentence case
 * - Formatting: URL-friendly slugs, phone number formatting, whitespace trimming, space collapsing
 * - Cleaning: strip HTML tags, remove non-alphanumeric characters
 * - Special: string reversal
 * Uses the gform_save_field_value filter to transform field data during the save process.
 *
 * FEATURES:
 * - Multiple transformations can be applied in sequence
 * - Highly configurable with 12+ built-in transformation options
 * - Form and field-specific targeting
 * - Preserves original value if transformation not applicable
 * - Sanitizes output for security
 *
 * CONFIGURATION REQUIRED:
 * - gform_save_field_value_3_16: Change '3' to your target form ID (or remove '_3' to apply to all forms)
 * - gform_save_field_value_3_16: Change '16' to your target field ID (or remove '_16' to apply to all fields on the form)
 * - $apply: Default is set to uppercase ('upper'). Other options include: 'lower', 'title', 'camel', 'pascal', 'sentence', 'slug', 'trim', 'collapse', 'alphanumeric', 'phone', 'strip_tags', 'reverse'. Add or modify transformations as needed.
 */

add_filter(
        'gform_save_field_value_3_16',
        function ( $value, $entry, $field ) {
            $transformations = [
                    'upper'        => 'strtoupper',           // ALL CAPS
                    'lower'        => 'strtolower',           // all lowercase
                    'title'        => 'ucwords',              // Title Case
                    'camel'        => 'lcfirst',              // camelCase (first char lower)
                    'pascal'       => 'ucfirst',              // PascalCase (first char upper)
                    'sentence'     => function ( $str ) {
                        // Sentence case
                        return ucfirst( strtolower( $str ) );
                    },
                    'slug'         => function ( $str ) {
                        // URL-friendly slug
                        return sanitize_title( $str );
                    },
                    'trim'         => 'trim',                 // Remove whitespace from ends
                    'collapse'     => function ( $str ) {
                        // Collapse multiple spaces to single
                        return preg_replace( '/\s+/', ' ', trim( $str ) );
                    },
                    'alphanumeric' => function ( $str ) {
                        // Remove non-alphanumeric chars
                        return preg_replace( '/[^A-Za-z0-9\s]/', '', $str );
                    },
                    'phone'        => function ( $str ) {
                        // Format as (XXX) XXX-XXXX
                        $cleaned = preg_replace( '/[^0-9]/', '', $str );
                        if ( strlen( $cleaned ) === 10 ) {
                            return sprintf( '(%s) %s-%s', substr( $cleaned, 0, 3 ), substr( $cleaned, 3, 3 ), substr( $cleaned, 6 ) );
                        }
                        return $str;
                    },
                    'strip_tags'   => 'strip_tags',           // Remove HTML/PHP tags
                    'reverse'      => 'strrev',               // Reverse the string
            ];

            if ( $field instanceof GF_Field && $field->get_input_type() === 'text' && ! empty( $value ) ) {
                // Apply multiple transformations in order
                $apply = [ 'upper' ];

                foreach ( $apply as $transform_type ) {
                    if ( isset( $transformations[ $transform_type ] ) ) {
                        $transformer = $transformations[ $transform_type ];
                        $value       = $transformer( $value );
                    }
                }

                $value = sanitize_text_field( $value );
            }
            return $value;
        },
        10,
        3
);
