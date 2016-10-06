<?php
if ( ! function_exists( 'has_shortcode' ) ) :
/**
 * Whether the passed content contains the specified shortcode.
 *
 * @since 3.6.0
 *
 * @param string $content
 * @param string $tag
 * @return boolean
 */
function has_shortcode( $content, $tag ) {
	if ( shortcode_exists( $tag ) ) {
		preg_match_all( '/' . get_shortcode_regex() . '/s', $content, $matches, PREG_SET_ORDER );
		
		if ( empty( $matches ) )
			return false;

		foreach ( $matches as $shortcode ) {
			if ( $tag === $shortcode[2] )
				return true;
		}
	}
	
	return false;
}
endif;

if ( ! function_exists( 'shortcode_exists' ) ) : 
/**
 * Whether a registered shortcode exists named $tag.
 *
 * @since 3.6.0
 *
 * @global array $shortcode_tags
 * @param string $tag
 * @return boolean
 */
function shortcode_exists( $tag ) {
	global $shortcode_tags;
	return array_key_exists( $tag, $shortcode_tags );
}
endif;