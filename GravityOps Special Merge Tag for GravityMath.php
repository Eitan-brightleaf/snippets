<?php
add_filter( 'gravityview/math/shortcode/before', function ( $formula ) {
	preg_match_all( '/~gos[._]{1,2}(\d+(?:\.\d+)*)[._]{1,2}([a-z_]+)~/', $formula, $matches, PREG_SET_ORDER );

	foreach ( $matches as $match ) {
		$field_id  = $match[1];
		$modifier  = $match[2];
		$replacement = sprintf( '{:%s:%s}', $field_id, $modifier );

		$formula = str_replace( $match[0], $replacement, $formula );
	}

	return $formula;
}, 9 );
