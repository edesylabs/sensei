<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * All functionality pertaining to the learner profiles in Sensei.
 *
 * @package Views
 * @author Automattic
 *
 * @since 1.4.0
 */
class Sensei_Learner_Profiles {
	/**
	 * Constructor.
	 *
	 * @since  1.4.0
	 */
	public function __construct() {
		// Setup permalink structure for learner profiles
		add_action( 'init', array( $this, 'setup_permastruct' ) );
		add_filter( 'wp_title', array( $this, 'page_title' ), 10, 2 );

		// Scripts for frontend.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Set heading for courses section of learner profiles
		add_action( 'sensei_learner_profile_info', array( $this, 'learner_profile_courses_heading' ), 30, 1 );

		// Add class to body tag
		add_filter( 'body_class', array( $this, 'learner_profile_body_class' ), 10, 1 );
	}

	/**
	 * Get the profile URL base.
	 *
	 * @return string
	 */
	private function get_profile_url_base() {
		return (string) apply_filters( 'sensei_learner_profiles_url_base', __( 'learner', 'sensei-lms' ) );
	}

	/**
	 * Enqueue frontend JavaScripts.
	 *
	 * @since  3.0.0
	 * @access private
	 */
	public function enqueue_scripts() {
		global $wp_query;

		if ( ! Sensei_Utils::get_setting_as_flag( 'js_disable', 'sensei_settings_js_disable' ) &&
			isset( $wp_query->query_vars['learner_profile'] ) ) {

			wp_enqueue_script( Sensei()->token . '-user-dashboard' );
		}
	}

	/**
	 * Setup permalink structure for learner profiles
	 *
	 * @since  1.4.0
	 * @return void
	 */
	public function setup_permastruct() {

		if ( isset( Sensei()->settings->settings['learner_profile_enable'] )
			&& Sensei()->settings->settings['learner_profile_enable'] ) {

			add_rewrite_rule( '^' . $this->get_profile_url_base() . '/([^/]*)/?', 'index.php?learner_profile=$matches[1]', 'top' );
			add_rewrite_tag( '%learner_profile%', '([^&]+)' );

		}
	}

	/**
	 * Adding page title for course results page
	 *
	 * @param  string $title Original title
	 * @param  string $sep   Seeparator string
	 * @return string        Modified title
	 */
	public function page_title( $title, $sep = null ) {
		global $wp_query;
		if ( isset( $wp_query->query_vars['learner_profile'] ) ) {
			$query_var    = $wp_query->query_vars['learner_profile'];
			$learner_user = Sensei_Learner::find_by_query_var( $query_var );
			if ( false === $learner_user ) {
				return $title;
			}

			$name = Sensei_Learner::get_full_name( $learner_user->ID );

			/**
			 * Filter the title for the learner profile page.
			 *
			 * @hook sensei_learner_profile_courses_heading
			 *
			 * @param {string} $title The title.
			 * @return {string} The filtered title.
			 */
			$title = apply_filters(
				'sensei_learner_profile_courses_heading',
				// translators: Placeholder is the name of the student.
				sprintf( __( 'Courses %s is taking', 'sensei-lms' ), $name )
			) . ' ' . $sep . ' ';
		}
		return $title;
	}

	/**
	 * Get permalink for learner profile
	 *
	 * @since  1.4.0
	 * @param  integer $user_id ID of user
	 * @return string           The learner profile permalink
	 */
	public function get_permalink( $user_id = 0 ) {
		$user = false;
		if ( $user_id == 0 ) {
			global $current_user;
			wp_get_current_user();
			$user = $current_user;
		} else {
			$user = get_userdata( $user_id );
		}

		$permalink = '';

		if ( $user ) {
			if ( get_option( 'permalink_structure' ) ) {
				$permalink = trailingslashit( get_home_url() ) . $this->get_profile_url_base() . '/' . $user->user_nicename;
			} else {
				$permalink = trailingslashit( get_home_url() ) . '?learner_profile=' . $user->user_nicename;
			}
		}

		/**
		 * This allows filtering of the Learner Profile permalinks.
		 *
		 * @since 1.9.13
		 *
		 * @hook sensei_learner_profile_permalink
		 *
		 * @param {string} $permalink The profile permalink.
		 * @param {object} $user      The user object.
		 * @return {string} The filtered permalink.
		 */
		return apply_filters( 'sensei_learner_profile_permalink', $permalink, $user );
	}

	/**
	 * Set heading for courses section of learner profiles
	 *
	 * @since  1.4.0
	 * @param  object $user Queried user object
	 * @return void
	 */
	public function learner_profile_courses_heading( $user ) {
		if ( strlen( $user->first_name ) > 0 ) {
			$name = $user->first_name;
		} else {
			$name = $user->display_name;
		}

		/**
		 * Filter the name for the heading in the courses section of the learner profile.
		 *
		 * @hook sensei_learner_profile_courses_heading_name
		 *
		 * @param {string} $name The name.
		 * @return {string} The filtered name.
		 */
		$name = apply_filters( 'sensei_learner_profile_courses_heading_name', $name );

		/**
		 * Filter the title for the learner profile page.
		 *
		 * @hook sensei_learner_profile_courses_heading
		 *
		 * @param {string} $title The title.
		 * @return {string} The filtered title.
		 */
		$title = apply_filters(
			'sensei_learner_profile_courses_heading',
			// translators: Placeholder is the name of the student.
			sprintf( __( 'Courses %s is taking', 'sensei-lms' ), $name )
		);
		echo '<h2>' . wp_kses_post( $title ) . '</h2>';
	}

	/**
	 * Load user info for learner profiles
	 *
	 * @since  1.4.0
	 * @param  object $user Queried user object
	 * @return void
	 */
	public static function user_info( $user ) {

		/**
		 * This hooke fires inside the Sensei_Learner_Profiles::user_info function.
		 * Just before the HTML is generated.
		 *
		 * @since 1.0.0
		 *
		 * @hook sensei_learner_profile_info
		 *
		 * @param {object} $user The user object.
		 */
		do_action( 'sensei_learner_profile_info', $user );

		/**
		 * This filter runs inside the Sensei_Learner_Profiles::user_info function.
		 * Here you can change the user avatar.
		 *
		 * @since 1.0.0
		 *
		 * @hook sensei_learner_profile_info_avatar
		 *
		 * @param {false|string} $user_avatar The user avatar.
		 * @param {int}          $user_id     The user ID.
		 * @return {false|string} The filtered avatar.
		 */
		$learner_avatar = apply_filters( 'sensei_learner_profile_info_avatar', get_avatar( $user->ID, 120 ), $user->ID );

		/**
		 * This filter runs inside the Sensei_Learner_Profiles::user_info function.
		 * Here you can change the learner profile user display name.
		 *
		 * @since 1.0.0
		 *
		 * @hook sensei_learner_profile_info_name
		 *
		 * @param {string} $user_display_name The user display name.
		 * @param {string} $user_id           The user ID.
		 * @return {string} The filtered display name.
		 */
		$learner_name = apply_filters( 'sensei_learner_profile_info_name', $user->display_name, $user->ID );

		/**
		 * This filter runs inside the Sensei_Learner_Profiles::user_info function.
		 * With this filter can change the users description on the learner user info
		 * output.
		 *
		 * @since 1.0.0
		 *
		 * @hook sensei_learner_profile_info_bio
		 *
		 * @param {string} $user_description The user description.
		 * @param {string} $user_id          The user ID.
		 * @return {string} The filtered description.
		 */
		$learner_bio = apply_filters( 'sensei_learner_profile_info_bio', $user->description, $user->ID );
		?>

		<div id="learner-info">

			<div class="learner-avatar"><?php echo wp_kses_post( $learner_avatar ); ?></div>

			<div class="learner-content">

				<h2><?php echo esc_html( $learner_name ); ?></h2>

				<div class="description"><?php echo wp_kses_post( wpautop( $learner_bio ) ); ?></div>

			</div>

		</div>

		<?php
	}

	/**
	 * Adding class to body tag
	 *
	 * @param  array $classes Existing classes
	 * @return array          Modified classes
	 */
	public function learner_profile_body_class( $classes ) {
		global $wp_query;
		if ( isset( $wp_query->query_vars['learner_profile'] ) ) {
			$classes[] = 'learner-profile';
		}
		return $classes;
	}

}

/**
 * Class WooThemes_Sensei_Learner_Profiles
 *
 * @ignore only for backward compatibility
 * @since 1.9.0
 */
class WooThemes_Sensei_Learner_Profiles extends Sensei_Learner_Profiles {}
