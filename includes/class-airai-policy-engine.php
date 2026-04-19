<?php
/**
 * AI Readiness Advisor - Policy Engine
 *
 * Handles:
 * - policy definitions
 * - recommendation logic
 * - wizard answer sanitization
 * - active policy persistence
 * - dynamic robots.txt injection
 *
 * @package AIReadinessAdvisor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AIRAI_Policy_Engine' ) ) {

	/**
	 * Core policy engine for AI policy recommendations and robots.txt output.
	 */
	class AIRAI_Policy_Engine {

		/**
		 * Get the option key used to store wizard and policy settings.
		 *
		 * @return string
		 */
		public static function option_key() {
			return defined( 'AIRAI_WIZARD_OPTION_KEY' ) ? AIRAI_WIZARD_OPTION_KEY : 'airai_wizard_settings_v1';
		}

		/**
		 * Get persisted wizard settings.
		 *
		 * @return array
		 */
		public static function get_settings() {
			$settings = get_option( self::option_key(), array() );

			if ( ! is_array( $settings ) ) {
				$settings = array();
			}

			$defaults = array(
				'wizard_completed' => false,
				'answers'          => array(),
				'recommended'      => 'balanced',
				'active_policy'    => '',
				'last_run'         => '',
			);

			return array_merge( $defaults, $settings );
		}

		/**
		 * Save wizard settings.
		 *
		 * @param array $settings Settings to persist.
		 * @return bool
		 */
		public static function save_settings( $settings ) {
			if ( ! is_array( $settings ) ) {
				return false;
			}

			return update_option( self::option_key(), $settings, false );
		}

		/**
		 * Return the available policy definitions.
		 *
		 * @return array
		 */
		public static function get_policies() {
			return array(
				'open' => array(
					'slug'        => 'open',
					'label'       => __( 'Open', 'ai-readiness-advisor' ),
					'summary'     => __( 'Best for maximum visibility and discovery.', 'ai-readiness-advisor' ),
					'best_for'    => __( 'Marketing sites, public blogs, educational resources, and sites that want the broadest discoverability.', 'ai-readiness-advisor' ),
					'tradeoff'    => __( 'Provides the least control over AI-related crawler access.', 'ai-readiness-advisor' ),
					'explainer'   => __( 'Choose this if your main goal is broad visibility and you are comfortable allowing major AI-related crawlers to access your public content.', 'ai-readiness-advisor' ),
					'allows'      => array( 'OAI-SearchBot', 'ChatGPT-User', 'GPTBot', 'CCBot', 'Google-Extended', 'Applebot-Extended' ),
					'blocks'      => array(),
					'robots_text' => "User-agent: *\nAllow: /\n",
				),
				'balanced' => array(
					'slug'        => 'balanced',
					'label'       => __( 'Balanced', 'ai-readiness-advisor' ),
					'summary'     => __( 'Best for most small business websites.', 'ai-readiness-advisor' ),
					'best_for'    => __( 'Service businesses, agencies, consultants, and publishers that want visibility with more control.', 'ai-readiness-advisor' ),
					'tradeoff'    => __( 'Still allows search and user-requested access, but limits some broader crawler access.', 'ai-readiness-advisor' ),
					'explainer'   => __( 'Choose this if you want your site to remain discoverable while limiting broader automated access that may not align with your goals.', 'ai-readiness-advisor' ),
					'allows'      => array( 'OAI-SearchBot', 'ChatGPT-User' ),
					'blocks'      => array( 'GPTBot' ),
					'robots_text' => "User-agent: OAI-SearchBot\nAllow: /\n\nUser-agent: ChatGPT-User\nAllow: /\n\nUser-agent: GPTBot\nDisallow: /\n",
				),
				'user-fetch-only' => array(
					'slug'        => 'user-fetch-only',
					'label'       => __( 'User Fetch Only', 'ai-readiness-advisor' ),
					'summary'     => __( 'Best when you want access only when a user explicitly requests it.', 'ai-readiness-advisor' ),
					'best_for'    => __( 'Professional services, niche websites, and cautious operators who want limited AI access.', 'ai-readiness-advisor' ),
					'tradeoff'    => __( 'Reduces passive discovery by AI-related crawlers.', 'ai-readiness-advisor' ),
					'explainer'   => __( 'Choose this if you want user-requested access but do not want broad automated discovery crawling.', 'ai-readiness-advisor' ),
					'allows'      => array( 'ChatGPT-User' ),
					'blocks'      => array( 'OAI-SearchBot', 'GPTBot', 'CCBot' ),
					'robots_text' => "User-agent: ChatGPT-User\nAllow: /\n\nUser-agent: OAI-SearchBot\nDisallow: /\n\nUser-agent: GPTBot\nDisallow: /\n\nUser-agent: CCBot\nDisallow: /\n",
				),
				'locked-down' => array(
					'slug'        => 'locked-down',
					'label'       => __( 'Locked Down', 'ai-readiness-advisor' ),
					'summary'     => __( 'Best when you want to block AI-related crawler access as much as possible.', 'ai-readiness-advisor' ),
					'best_for'    => __( 'Premium publishers, privacy-sensitive sites, and organizations that want the strongest available policy signal.', 'ai-readiness-advisor' ),
					'tradeoff'    => __( 'Can reduce discoverability in AI-assisted experiences.', 'ai-readiness-advisor' ),
					'explainer'   => __( 'Choose this if control matters more than AI visibility and you prefer a restrictive posture.', 'ai-readiness-advisor' ),
					'allows'      => array(),
					'blocks'      => array( 'OAI-SearchBot', 'ChatGPT-User', 'GPTBot', 'CCBot', 'Google-Extended', 'Applebot-Extended' ),
					'robots_text' => "User-agent: OAI-SearchBot\nDisallow: /\n\nUser-agent: ChatGPT-User\nDisallow: /\n\nUser-agent: GPTBot\nDisallow: /\n\nUser-agent: CCBot\nDisallow: /\n\nUser-agent: Google-Extended\nDisallow: /\n\nUser-agent: Applebot-Extended\nDisallow: /\n",
				),
			);
		}

		/**
		 * Sanitize wizard answers coming from the browser.
		 *
		 * @param array $answers Raw answers.
		 * @return array
		 */
		public static function sanitize_answers( $answers ) {
			if ( ! is_array( $answers ) ) {
				return array();
			}

			$clean = array(
				'primary_goal'  => '',
				'site_type'     => '',
				'caution_level' => '',
			);

			if ( isset( $answers['primary_goal'] ) ) {
				$clean['primary_goal'] = sanitize_key( wp_unslash( $answers['primary_goal'] ) );
			}

			if ( isset( $answers['site_type'] ) ) {
				$clean['site_type'] = sanitize_key( wp_unslash( $answers['site_type'] ) );
			}

			if ( isset( $answers['caution_level'] ) ) {
				$clean['caution_level'] = sanitize_key( wp_unslash( $answers['caution_level'] ) );
			}

			return $clean;
		}

		/**
		 * Recommend a policy based on wizard answers.
		 *
		 * @param array $answers Sanitized answers.
		 * @return string
		 */
		public static function recommend_policy( $answers ) {
			$goal    = isset( $answers['primary_goal'] ) ? $answers['primary_goal'] : '';
			$type    = isset( $answers['site_type'] ) ? $answers['site_type'] : '';
			$caution = isset( $answers['caution_level'] ) ? $answers['caution_level'] : '';

			if ( 'block_ai' === $goal || 'high' === $caution || in_array( $type, array( 'premium', 'sensitive' ), true ) ) {
				return 'locked-down';
			}

			if ( 'user_requested_only' === $goal ) {
				return 'user-fetch-only';
			}

			if ( 'maximum_visibility' === $goal && 'low' === $caution ) {
				return 'open';
			}

			return 'balanced';
		}

		/**
		 * Get the currently active policy.
		 *
		 * @return string
		 */
		public static function get_active_policy() {
			$settings = self::get_settings();
			return isset( $settings['active_policy'] ) ? sanitize_key( $settings['active_policy'] ) : '';
		}

		/**
		 * Set the currently active policy.
		 *
		 * @param string $policy_slug Policy slug.
		 * @return bool
		 */
		public static function set_active_policy( $policy_slug ) {
			$policy_slug = sanitize_key( $policy_slug );
			$policies    = self::get_policies();

			if ( ! isset( $policies[ $policy_slug ] ) ) {
				return false;
			}

			$settings                     = self::get_settings();
			$settings['active_policy']    = $policy_slug;
			$settings['wizard_completed'] = true;
			$settings['last_run']         = current_time( 'mysql' );

			return self::save_settings( $settings );
		}

		/**
		 * Save wizard answers and recommendation.
		 *
		 * @param array  $answers     Sanitized answers.
		 * @param string $recommended Recommended policy slug.
		 * @return bool
		 */
		public static function save_wizard_progress( $answers, $recommended ) {
			$recommended = sanitize_key( $recommended );
			$policies    = self::get_policies();

			if ( ! isset( $policies[ $recommended ] ) ) {
				$recommended = 'balanced';
			}

			$settings                = self::get_settings();
			$settings['answers']     = $answers;
			$settings['recommended'] = $recommended;
			$settings['last_run']    = current_time( 'mysql' );

			return self::save_settings( $settings );
		}

		/**
		 * Inject the active AI policy into dynamic robots.txt output.
		 *
		 * @param string $output Current robots.txt output.
		 * @param bool   $public Whether the site is public.
		 * @return string
		 */
		public static function filter_robots_txt( $output, $public ) {
			$active = self::get_active_policy();

			if ( empty( $active ) ) {
				return $output;
			}

			$policies = self::get_policies();

			if ( ! isset( $policies[ $active ] ) ) {
				return $output;
			}

			$block  = "\n# AI Readiness Advisor policy: " . $active . "\n";
			$block .= trim( $policies[ $active ]['robots_text'] ) . "\n";

			$output = trim( (string) $output );

			return $output . "\n\n" . $block;
		}
	}

	add_filter( 'robots_txt', array( 'AIRAI_Policy_Engine', 'filter_robots_txt' ), 20, 2 );
}
