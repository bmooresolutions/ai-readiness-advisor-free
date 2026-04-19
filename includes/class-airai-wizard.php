<?php
/**
 * AI Readiness Advisor - Wizard
 *
 * Adds a guided setup wizard that:
 * - educates the user
 * - collects goals
 * - recommends a policy
 * - applies the chosen policy through dynamic robots.txt output
 *
 * @package AIReadinessAdvisor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AIRAI_Wizard' ) ) {

	/**
	 * Wizard controller.
	 */
	class AIRAI_Wizard {

		/**
		 * Initialize hooks.
		 *
		 * @return void
		 */
		public static function init() {
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

			add_action( 'wp_ajax_airai_get_wizard_state', array( __CLASS__, 'ajax_get_wizard_state' ) );
			add_action( 'wp_ajax_airai_save_wizard_answers', array( __CLASS__, 'ajax_save_wizard_answers' ) );
			add_action( 'wp_ajax_airai_apply_wizard_policy', array( __CLASS__, 'ajax_apply_wizard_policy' ) );
			add_action( 'wp_ajax_airai_reset_wizard', array( __CLASS__, 'ajax_reset_wizard' ) );
		}

		/**
		 * Register wizard submenu page.
		 *
		 * @return void
		 */
		public static function register_menu() {
			add_submenu_page(
				'airai-dashboard',
				__( 'Setup Wizard', 'ai-readiness-advisor' ),
				__( 'Setup Wizard', 'ai-readiness-advisor' ),
				'manage_options',
				'airai-wizard',
				array( __CLASS__, 'render_page' )
			);
		}

		/**
		 * Render the wizard root.
		 *
		 * @return void
		 */
		public static function render_page() {
			?>
			<div class="wrap">
				<h1><?php echo esc_html__( 'AI Readiness Setup Wizard', 'ai-readiness-advisor' ); ?></h1>
				<p><?php echo esc_html__( 'This wizard helps you understand your site posture, choose your goals, and apply a recommended AI access policy.', 'ai-readiness-advisor' ); ?></p>
				<div id="airai-wizard-app"></div>
			</div>
			<?php
		}

		/**
		 * Enqueue wizard assets.
		 *
		 * @param string $hook Current admin hook.
		 * @return void
		 */
		public static function enqueue_assets( $hook ) {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

	if ( 'airai-wizard' !== $page ) {
		return;
	}

	$version = defined( 'AIRAI_VERSION' ) ? AIRAI_VERSION : '2.3.0';

	wp_enqueue_script(
		'airai-admin-wizard',
		plugin_dir_url( dirname( __FILE__ ) . '/placeholder' ) . 'assets/admin-wizard.js',
		array(),
		$version,
		true
	);

	$settings = AIRAI_Policy_Engine::get_settings();
	$policies = AIRAI_Policy_Engine::get_policies();

	wp_localize_script(
		'airai-admin-wizard',
		'AIRAI_WIZARD',
		array(
			'ajaxurl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'airai_wizard_ajax' ),
			'currentPage' => $page,
			'policies'    => $policies,
			'settings'    => $settings,
			'playground'  => function_exists( 'airai_is_playground' ) ? airai_is_playground() : false,
			'i18n'        => array(
				'loading' => __( 'Loading wizard...', 'ai-readiness-advisor' ),
				'error'   => __( 'The wizard could not be loaded.', 'ai-readiness-advisor' ),
			),
		)
	);
}
		/**
		 * Enforce nonce and capability checks for all wizard AJAX actions.
		 *
		 * @return void
		 */
		protected static function require_permissions() {
			check_ajax_referer( 'airai_wizard_ajax' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'Insufficient permissions.', 'ai-readiness-advisor' ),
					),
					403
				);
			}
		}

		/**
		 * Build a human-friendly summary of the current audit posture.
		 *
		 * @return array
		 */
		protected static function get_audit_summary() {
			$summary = array(
				'readiness_score' => 0,
				'robots_present'  => false,
				'sitemap_present' => false,
				'posture'         => __( 'Unclear', 'ai-readiness-advisor' ),
				'explainers'      => array(),
			);

			if ( function_exists( 'airai_collect_dashboard_state' ) ) {
				$state = airai_collect_dashboard_state();

				$summary['readiness_score'] = isset( $state['readiness']['score'] ) ? absint( $state['readiness']['score'] ) : 0;
				$summary['robots_present']  = ! empty( $state['servedRobots'] );
				$summary['sitemap_present'] = ! empty( $state['sitemap']['wp']['found'] );

				if ( ! empty( $state['verification'] ) ) {
					$posture = 'mixed';

					foreach ( $state['verification'] as $row ) {
						if ( isset( $row['ua'], $row['allowed'] ) && 'GPTBot' === $row['ua'] && false === $row['allowed'] ) {
							$posture = 'controlled';
							break;
						}
					}

					$summary['posture'] = ucfirst( $posture );
				}

				$summary['explainers'] = array(
					$summary['robots_present']
						? __( 'Your site is serving a robots.txt policy that can be used to communicate crawler preferences.', 'ai-readiness-advisor' )
						: __( 'Your site does not appear to be serving a robots.txt policy yet.', 'ai-readiness-advisor' ),
					$summary['sitemap_present']
						? __( 'Your site exposes a sitemap, which can help search systems discover public content.', 'ai-readiness-advisor' )
						: __( 'A sitemap was not detected, so discoverability may be weaker than it could be.', 'ai-readiness-advisor' ),
				);
			}

			return $summary;
		}

		/**
		 * Return wizard state.
		 *
		 * @return void
		 */
		public static function ajax_get_wizard_state() {
			self::require_permissions();

			wp_send_json_success(
				array(
					'audit'    => self::get_audit_summary(),
					'settings' => AIRAI_Policy_Engine::get_settings(),
					'policies' => AIRAI_Policy_Engine::get_policies(),
				)
			);
		}

		/**
		 * Save wizard answers and recommendation.
		 *
		 * @return void
		 */
		public static function ajax_save_wizard_answers() {
			self::require_permissions();

			$answers = isset( $_POST['answers'] ) ? AIRAI_Policy_Engine::sanitize_answers( wp_unslash( $_POST['answers'] ) ) : array();

			$recommended = AIRAI_Policy_Engine::recommend_policy( $answers );
			AIRAI_Policy_Engine::save_wizard_progress( $answers, $recommended );

			$policies = AIRAI_Policy_Engine::get_policies();

			wp_send_json_success(
				array(
					'answers'     => $answers,
					'recommended' => $recommended,
					'policy'      => isset( $policies[ $recommended ] ) ? $policies[ $recommended ] : array(),
				)
			);
		}

		/**
		 * Apply the selected wizard policy.
		 *
		 * @return void
		 */
		public static function ajax_apply_wizard_policy() {
			self::require_permissions();

			$policy = isset( $_POST['policy'] ) ? sanitize_key( wp_unslash( $_POST['policy'] ) ) : '';

			if ( ! AIRAI_Policy_Engine::set_active_policy( $policy ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'The selected policy could not be applied.', 'ai-readiness-advisor' ),
					),
					400
				);
			}

			$policies = AIRAI_Policy_Engine::get_policies();

			wp_send_json_success(
				array(
					'active_policy' => $policy,
					'policy'        => isset( $policies[ $policy ] ) ? $policies[ $policy ] : array(),
					'message'       => __( 'Policy applied through WordPress dynamic robots.txt output.', 'ai-readiness-advisor' ),
				)
			);
		}

		/**
		 * Reset the wizard so the user can run it again.
		 *
		 * @return void
		 */
		public static function ajax_reset_wizard() {
			self::require_permissions();

			AIRAI_Policy_Engine::save_settings(
				array(
					'wizard_completed' => false,
					'answers'          => array(),
					'recommended'      => 'balanced',
					'active_policy'    => '',
					'last_run'         => current_time( 'mysql' ),
				)
			);

			wp_send_json_success(
				array(
					'message' => __( 'Wizard reset complete.', 'ai-readiness-advisor' ),
				)
			);
		}
	}

	AIRAI_Wizard::init();
}
