<?php
/**
 * Transactional Emails Controller
 *
 * Sensei Emails Class which handles the sending emails and email templates. This class loads in available emails.
 *
 * @package Users
 * @author Automattic
 */
class Sensei_Emails {

	/**
	 * @var array Array of email notification classes.
	 * @access public
	 */
	public $emails;

	/**
	 * @var string Stores the emailer's address.
	 * @access private
	 */
	private $_from_address;

	/**
	 * @var string Stores the emailer's name.
	 * @access private
	 */
	private $_from_name;

	/**
	 * @var mixed Content type for sent emails
	 * @access private
	 */
	private $_content_type;

	/**
	 * Constructor for the email class hooks in all emails that can be sent.
	 */
	function __construct( $file ) {

		$this->init();

		// Hooks for sending emails during Sensei events
		add_action( 'sensei_user_quiz_grade', array( $this, 'learner_graded_quiz' ), 10, 4 );
		add_action( 'sensei_course_status_updated', array( $this, 'learner_completed_course' ), 10, 4 );
		add_action( 'sensei_course_status_updated', array( $this, 'teacher_completed_course' ), 10, 4 );
		add_action( 'sensei_user_course_start', array( $this, 'teacher_started_course' ), 10, 2 );
		add_action( 'sensei_user_lesson_end', array( $this, 'teacher_completed_lesson' ), 10, 2 );
		add_action( 'sensei_user_quiz_submitted', array( $this, 'teacher_quiz_submitted' ), 10, 5 );
		add_action( 'sensei_new_private_message', array( $this, 'teacher_new_message' ), 10, 1 );
		add_action( 'sensei_private_message_reply', array( $this, 'new_message_reply' ), 10, 2 );

		/**
		 * Action hook to allow 3rd parties to unhook Sensei's email actions.
		 *
		 * @hook sensei_emails
		 *
		 * @param {Sensei_Emails} $emails The Sensei_Emails object.
		 */
		do_action( 'sensei_emails', $this );
	}

	/**
	 * Init email classes
	 */
	function init() {

		$this->emails['learner-graded-quiz']      = new Sensei_Email_Learner_Graded_Quiz();
		$this->emails['learner-completed-course'] = new Sensei_Email_Learner_Completed_Course();
		$this->emails['teacher-completed-course'] = new Sensei_Email_Teacher_Completed_Course();
		$this->emails['teacher-started-course']   = new Sensei_Email_Teacher_Started_Course();
		$this->emails['teacher-completed-lesson'] = new Sensei_Email_Teacher_Completed_Lesson();
		$this->emails['teacher-quiz-submitted']   = new Sensei_Email_Teacher_Quiz_Submitted();
		$this->emails['teacher-new-message']      = new Sensei_Email_Teacher_New_Message();
		$this->emails['new-message-reply']        = new Sensei_Email_New_Message_Reply();
		/**
		 * Filter Sensei's email classes.
		 *
		 * @hook sensei_email_classes
		 *
		 * @param {array} $emails Array of email classes.
		 * @return {array} Filtered array of email classes.
		 */
		$this->emails = apply_filters( 'sensei_email_classes', $this->emails );
	}

	/**
	 * Return the email classes - used in admin to load settings.
	 *
	 * @access public
	 * @return array
	 */
	function get_emails() {
		return $this->emails;
	}

	/**
	 * Get from name for email.
	 *
	 * @access public
	 * @return string
	 */
	function get_from_name() {

		if ( ! $this->_from_name ) {
			if ( isset( Sensei()->settings->settings['email_from_name'] ) && '' != Sensei()->settings->settings['email_from_name'] ) {
				$this->_from_name = Sensei()->settings->settings['email_from_name'];
			} else {
				$this->_from_name = get_bloginfo( 'name' );
			}
		}

		return wp_specialchars_decode( $this->_from_name );
	}

	/**
	 * Get from email address.
	 *
	 * @access public
	 * @return string
	 */
	function get_from_address() {

		if ( ! $this->_from_address ) {
			if ( isset( Sensei()->settings->settings['email_from_address'] ) && '' != Sensei()->settings->settings['email_from_address'] ) {
				$this->_from_address = Sensei()->settings->settings['email_from_address'];
			} else {
				$this->_from_address = get_bloginfo( 'admin_email' );
			}
		}

		return $this->_from_address;
	}

	/**
	 * Get the content type for the email.
	 *
	 * @access public
	 * @return string
	 */
	function get_content_type() {
		return $this->_content_type;
	}

	/**
	 * Wraps a message in the sensei mail template.
	 *
	 * @access public
	 * @param mixed $content
	 * @return string
	 */
	function wrap_message( $content ) {

		$html = '';

		$html .= $this->load_template( 'header' );
		$html .= wpautop( wptexturize( $content ) );
		$html .= $this->load_template( 'footer' );

		return $html;
	}

	/**
	 * Send the email.
	 *
	 * @access public
	 * @param mixed  $to
	 * @param mixed  $subject
	 * @param mixed  $message
	 * @param string $headers (default: "Content-Type: text/html\r\n")
	 * @param string $attachments (default: "")
	 * @param string $content_type (default: "text/html")
	 * @return void
	 */
	function send( $to, $subject, $message, $headers = "Content-Type: text/html\r\n", $attachments = '', $content_type = 'text/html' ) {
		// Set content type
		$this->_content_type = $content_type;

		// Filters for the email.
		add_filter( 'wp_mail_from', array( $this, 'get_from_address' ) );
		add_filter( 'wp_mail_from_name', array( $this, 'get_from_name' ) );
		add_filter( 'wp_mail_content_type', array( $this, 'get_content_type' ) );

		/**
		 * Filter Sensei's ability to send out emails.
		 *
		 * @since 1.8.0
		 * @since 1.24.0 The `$identifier` parameter was added.
		 *
		 * @hook sensei_send_emails
		 *
		 * @param {bool}   $send_email   Whether to send the email or not.
		 * @param {mixed}  $to           The email address(es) to send the email to.
		 * @param {mixed}  $subject      The subject of the email.
		 * @param {mixed}  $message      The message of the email.
		 * @param {string} $identifier   Unique identifier of the email, not for legacy emails.
		 * @param {array}  $replacements The replacements values for the email, not for legacy emails.
		 *
		 * @return {bool} Whether to send the email or not.
		 */
		if ( apply_filters( 'sensei_send_emails', true, $to, $subject, $message, 'legacy-email', [] ) ) {

			wp_mail( $to, $subject, $message, $headers, $attachments );

		}

		// Unhook filters.
		remove_filter( 'wp_mail_from', array( $this, 'get_from_address' ) );
		remove_filter( 'wp_mail_from_name', array( $this, 'get_from_name' ) );
		remove_filter( 'wp_mail_content_type', array( $this, 'get_content_type' ) );
	}

	function get_content( $email_template ) {

		$message = $this->load_template( $email_template );

		$html = $this->wrap_message( $message );

		/**
		 * Filter the email content.
		 *
		 * @hook sensei_email
		 *
		 * @param {string} $html The email content.
		 * @param {string} $email_template The email template.
		 * @return {string} Filtered email content.
		 */
		return apply_filters( 'sensei_email', $html, $email_template );
	}

	function load_template( $template = '' ) {
		global  $email_template;

		if ( ! $template ) {
			return;
		}

		$email_template = $template . '.php';
		$template       = Sensei_Templates::template_loader( '' );

		ob_start();

		/**
		 * Action hook before email template is loaded.
		 *
		 * @hook sensei_before_email_template
		 *
		 * @param {string} $email_template The email template.
		 */
		do_action( 'sensei_before_email_template', $email_template );

		include $template;

		/**
		 * Action hook after email template is loaded.
		 *
		 * @hook sensei_after_email_template
		 *
		 * @param {string} $email_template The email template.
		 */
		do_action( 'sensei_after_email_template', $email_template );

		$email_template = '';
		return ob_get_clean();
	}

	/**
	 * Send email to learner on quiz grading (auto or manual)
	 *
	 * @access public
	 * @return void
	 */
	function learner_graded_quiz( $user_id, $quiz_id, $grade, $passmark ) {

		$email_type = 'learner-graded-quiz';
		$send       = false;

		if ( isset( Sensei()->settings->settings['email_learners'] ) ) {
			if ( in_array( $email_type, (array) Sensei()->settings->settings['email_learners'], true ) ) {
				$send = true;
			}
		} else {
			$send = true;
		}

		if ( $send ) {
			$email = $this->emails[ $email_type ];
			$email->trigger( $user_id, $quiz_id, $grade, $passmark );

			sensei_log_event( 'email_send', [ 'type' => $email_type ] );
		}
	}

	/**
	 * Send email to learner on course completion
	 *
	 * @access public
	 * @return void
	 */
	function learner_completed_course( $status = 'in-progress', $user_id = 0, $course_id = 0, $comment_id = 0 ) {

		if ( 'complete' !== $status ) {
			return;
		}

		$email_type = 'learner-completed-course';
		$send       = false;

		if ( isset( Sensei()->settings->settings['email_learners'] ) ) {
			if ( in_array( $email_type, (array) Sensei()->settings->settings['email_learners'], true ) ) {
				$send = true;
			}
		} else {
			$send = true;
		}

		if ( $send ) {
			$email = $this->emails[ $email_type ];
			$email->trigger( $user_id, $course_id );

			sensei_log_event( 'email_send', [ 'type' => $email_type ] );
		}
	}

	/**
	 * Send email to teacher on course completion
	 *
	 * @access public
	 * @return void
	 */
	function teacher_completed_course( $status = 'in-progress', $learner_id = 0, $course_id = 0, $comment_id = 0 ) {

		if ( 'complete' !== $status ) {
			return;
		}

		$email_type = 'teacher-completed-course';
		$send       = false;

		if ( isset( Sensei()->settings->settings['email_teachers'] ) ) {
			if ( in_array( $email_type, (array) Sensei()->settings->settings['email_teachers'], true ) ) {
				$send = true;
			}
		} else {
			$send = true;
		}

		if ( $send ) {
			$email = $this->emails[ $email_type ];
			$email->trigger( $learner_id, $course_id );

			sensei_log_event( 'email_send', [ 'type' => $email_type ] );
		}
	}

	/**
	 * Send email to teacher on course beginning
	 *
	 * @access public
	 * @return void
	 */
	function teacher_started_course( $learner_id = 0, $course_id = 0 ) {

		$email_type = 'teacher-started-course';
		$send       = false;

		if ( isset( Sensei()->settings->settings['email_teachers'] ) ) {
			if ( in_array( $email_type, (array) Sensei()->settings->settings['email_teachers'], true ) ) {
				$send = true;
			}
		} else {
			$send = true;
		}

		if ( $send ) {
			$email = $this->emails[ $email_type ];
			$email->trigger( $learner_id, $course_id );

			sensei_log_event( 'email_send', [ 'type' => $email_type ] );
		}
	}

	/**
	 * teacher_completed_lesson()
	 *
	 * Send email to teacher on student completing lesson
	 *
	 * @access public
	 * @return void
	 * @since 1.9.0
	 */
	function teacher_completed_lesson( $learner_id = 0, $lesson_id = 0 ) {

		$email_type = 'teacher-completed-lesson';
		$send       = false;

		if ( isset( Sensei()->settings->settings['email_teachers'] ) ) {
			if ( in_array( $email_type, (array) Sensei()->settings->settings['email_teachers'], true ) ) {
				$send = true;
			}
		} else {
			$send = true;
		}

		if ( $send ) {
			$email = $this->emails[ $email_type ];
			$email->trigger( $learner_id, $lesson_id );

			sensei_log_event( 'email_send', [ 'type' => $email_type ] );
		}
	}

	/**
	 * Send email to teacher on quiz submission
	 *
	 * @param int    $learner_id
	 * @param int    $quiz_id
	 * @param int    $grade
	 * @param int    $passmark
	 * @param string $quiz_grade_type
	 */
	function teacher_quiz_submitted( $learner_id = 0, $quiz_id = 0, $grade = 0, $passmark = 0, $quiz_grade_type = 'manual' ) {

		$email_type = 'teacher-quiz-submitted';
		$send       = false;

		// Only trigger if the quiz was marked as manual grading, or auto grading didn't complete
		if ( 'manual' === $quiz_grade_type || is_wp_error( $grade ) ) {
			if ( isset( Sensei()->settings->settings['email_teachers'] ) ) {
				if ( in_array( $email_type, (array) Sensei()->settings->settings['email_teachers'], true ) ) {
					$send = true;
				}
			} else {
				$send = true;
			}

			if ( $send ) {
				$email = $this->emails[ $email_type ];
				$email->trigger( $learner_id, $quiz_id );

				sensei_log_event( 'email_send', [ 'type' => $email_type ] );
			}
		}
	}

	/**
	 * Send email to teacher when a new private message is received
	 *
	 * @access public
	 * @return void
	 */
	function teacher_new_message( $message_id = 0 ) {

		$email_type = 'teacher-new-message';
		$send       = false;

		if ( isset( Sensei()->settings->settings['email_teachers'] ) ) {
			if ( in_array( $email_type, (array) Sensei()->settings->settings['email_teachers'], true ) ) {
				$send = true;
			}
		} else {
			$send = true;
		}

		if ( $send ) {
			$email = $this->emails[ $email_type ];
			$email->trigger( $message_id );

			sensei_log_event( 'email_send', [ 'type' => $email_type ] );
		}
	}

	/**
	 * Send email to a user when their private message receives a reply
	 *
	 * @access public
	 * @return void
	 */
	function new_message_reply( $comment, $message ) {

		$email_type = 'new-message-reply';
		$send       = false;

		if ( isset( Sensei()->settings->settings['email_global'] ) ) {
			if ( in_array( $email_type, (array) Sensei()->settings->settings['email_global'], true ) ) {
				$send = true;
			}
		} else {
			$send = true;
		}

		if ( $send ) {
			$email = $this->emails[ $email_type ];
			$email->trigger( $comment, $message );

			sensei_log_event( 'email_send', [ 'type' => $email_type ] );
		}
	}

}

/**
 * Class WooThemes_Sensei_Emails
 *
 * @ignore only for backward compatibility
 * @since 1.9.0
 */
class WooThemes_Sensei_Emails extends Sensei_Emails{}
