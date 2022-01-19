<?php
/**
 * File containing the Site_Logo class.
 *
 * @package sensei
 * @since
 */

namespace Sensei\Blocks\Course_Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Display the site logo, linking to the course page.
 */
class Site_Logo {

	/**
	 * Site_Logo constructor.
	 */
	public function __construct() {
		if ( ! \WP_Block_Type_Registry::get_instance()->is_registered( 'core/site-logo' ) ) {
			register_block_type(
				'core/site-logo',
				[
					'render_callback' => [ $this, 'render_site_logo' ],
				]
			);
		}
	}

	/**
	 * Renders the block.
	 *
	 * @param array $attributes The block attributes.
	 *
	 * @access private
	 *
	 * @return string The block HTML.
	 */
	public function render_site_logo( array $attributes ): string {

		$course_id      = \Sensei_Utils::get_current_course();
		$custom_logo_id = get_theme_mod( 'custom_logo' );

		if ( ! $course_id || ! $custom_logo_id ) {
			return '';
		}

		$logo = wp_get_attachment_image( $custom_logo_id, 'medium', false );

		$wrapper_attributes = '';
		if ( function_exists( 'get_block_wrapper_attributes' ) ) {
			$wrapper_attributes = get_block_wrapper_attributes( $attributes );
		}

		return sprintf( '<a href="%1$s" %2$s>%3$s</a>', get_the_permalink( $course_id ), $wrapper_attributes, $logo );
	}
}
