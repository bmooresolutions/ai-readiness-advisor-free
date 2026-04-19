<?php
/**
 * Plugin Name: AI Readiness Advisor
 * Plugin URI: https://www.bmooresolutions.com/tools/ai-readiness-advisor
 * Description: Audit AI crawler access, inspect robots.txt and sitemaps, simulate crawler behavior, and monitor known AI-related traffic.
 * Version: 2.2.0
 * Requires at least: 5.8
 * Tested up to: 6.6
 * Requires PHP: 7.4
 * Author: BMoore Solutions
 * Author URI: https://bmooresolutions.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-readiness-advisor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AIRAI_VERSION = '2.2.0';
const AIRAI_OPT_KEY = 'airai_options_v4';
const AIRAI_LOG_KEY = 'airai_bot_log_v3';

if ( ! defined( 'AIRAI_WIZARD_OPTION_KEY' ) ) {
	define( 'AIRAI_WIZARD_OPTION_KEY', 'airai_wizard_settings_v1' );
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-airai-policy-engine.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-airai-wizard.php';

function airai_default_options() {
	return array(
		'enable_bot_logging' => true,
		'log_limit'          => 500,
		'ui_theme'           => 'default',
	);
}

function airai_get_options() {
	$opts = get_option( AIRAI_OPT_KEY );
	if ( ! is_array( $opts ) ) {
		$opts = array();
	}
	return array_merge( airai_default_options(), $opts );
}

function airai_activate() {
	if ( false === get_option( AIRAI_OPT_KEY, false ) ) {
		add_option( AIRAI_OPT_KEY, airai_default_options() );
	}
	if ( false === get_option( AIRAI_LOG_KEY, false ) ) {
		add_option( AIRAI_LOG_KEY, array(), '', false );
	}
}
register_activation_hook( __FILE__, 'airai_activate' );

function airai_uninstall() {
	delete_option( AIRAI_OPT_KEY );
	delete_option( AIRAI_LOG_KEY );
}
register_uninstall_hook( __FILE__, 'airai_uninstall' );

function airai_is_playground() {
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	return is_string( $host ) && false !== strpos( $host, 'playground.wordpress.net' );
}

function airai_server_http_user_agent() {
	return sanitize_text_field( wp_unslash( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ) );
}

function airai_server_remote_addr() {
	return sanitize_text_field( wp_unslash( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ) );
}

function airai_server_request_uri() {
	return sanitize_text_field( wp_unslash( (string) ( $_SERVER['REQUEST_URI'] ?? '' ) ) );
}

function airai_server_http_host() {
	return sanitize_text_field( wp_unslash( (string) ( $_SERVER['HTTP_HOST'] ?? '' ) ) );
}

function airai_sanitize_path( $raw ) {
	$val = is_string( $raw ) ? $raw : '/';
	$val = wp_unslash( $val );
	$val = preg_replace( '~[^A-Za-z0-9\-\._~!\$&\'\(\)\*\+,;=:@%/]+~', '', $val );
	if ( '' === $val ) {
		$val = '/';
	}
	if ( '/' !== $val[0] ) {
		$val = '/' . $val;
	}
	if ( strlen( $val ) > 1024 ) {
		$val = substr( $val, 0, 1024 );
	}
	return sanitize_text_field( $val );
}

function airai_known_agents() {
	return array(
		'OAI-SearchBot' => array(
			'token'       => 'OAI-SearchBot',
			'label'       => 'OpenAI Search',
			'type'        => 'crawler',
			'description' => 'Crawler used for search and discovery access.',
		),
		'ChatGPT-User' => array(
			'token'       => 'ChatGPT-User',
			'label'       => 'ChatGPT User Fetcher',
			'type'        => 'fetcher',
			'description' => 'User-triggered fetches on behalf of a ChatGPT user.',
		),
		'GPTBot' => array(
			'token'       => 'GPTBot',
			'label'       => 'OpenAI GPTBot',
			'type'        => 'crawler',
			'description' => 'Crawler token commonly used for OpenAI training access control.',
		),
		'PerplexityBot' => array(
			'token'       => 'PerplexityBot',
			'label'       => 'PerplexityBot',
			'type'        => 'crawler',
			'description' => 'Crawler associated with Perplexity discovery.',
		),
		'CCBot' => array(
			'token'       => 'CCBot',
			'label'       => 'Common Crawl',
			'type'        => 'crawler',
			'description' => 'Common Crawl web archive crawler.',
		),
		'Google-Extended' => array(
			'token'       => 'Google-Extended',
			'label'       => 'Google-Extended',
			'type'        => 'policy',
			'description' => 'Robots.txt policy token for Google AI usage preferences.',
		),
		'Applebot-Extended' => array(
			'token'       => 'Applebot-Extended',
			'label'       => 'Applebot-Extended',
			'type'        => 'policy',
			'description' => 'Robots.txt policy token for Apple AI usage preferences.',
		),
	);
}

function airai_robot_agents_by_type( $type ) {
	$out = array();
	foreach ( airai_known_agents() as $agent ) {
		if ( $type === $agent['type'] ) {
			$out[] = $agent;
		}
	}
	return $out;
}

function airai_strip_inline_comment( $line ) {
	$hash_pos = strpos( $line, '#' );
	if ( false !== $hash_pos ) {
		$line = substr( $line, 0, $hash_pos );
	}
	return trim( $line );
}

function airai_robots_parse_groups( $robots ) {
	$robots = str_replace( array( "\r\n", "\r" ), "\n", (string) $robots );
	$lines  = explode( "\n", $robots );
	$groups = array();
	$current = array(
		'uas'        => array(),
		'rules'      => array(),
		'directives' => array(),
	);
	$seen_rule = false;

	foreach ( $lines as $raw ) {
		$line = airai_strip_inline_comment( trim( (string) $raw ) );
		if ( '' === $line ) {
			continue;
		}

		$parts = explode( ':', $line, 2 );
		if ( count( $parts ) < 2 ) {
			continue;
		}

		$field = strtolower( trim( $parts[0] ) );
		$value = trim( $parts[1] );

		if ( 'user-agent' === $field ) {
			if ( ! empty( $current['uas'] ) && $seen_rule ) {
				$groups[] = $current;
				$current  = array(
					'uas'        => array(),
					'rules'      => array(),
					'directives' => array(),
				);
				$seen_rule = false;
			}
			$current['uas'][] = $value;
			continue;
		}

		if ( empty( $current['uas'] ) ) {
			$current['uas'][] = '*';
		}

		if ( in_array( $field, array( 'allow', 'disallow' ), true ) ) {
			$current['rules'][] = array(
				'type'    => $field,
				'pattern' => $value,
			);
			$seen_rule = true;
			continue;
		}

		$current['directives'][] = array(
			'field' => $field,
			'value' => $value,
		);
	}

	if ( ! empty( $current['uas'] ) || ! empty( $current['rules'] ) || ! empty( $current['directives'] ) ) {
		$groups[] = $current;
	}

	return $groups;
}

function airai_robots_best_group_for_ua( $groups, $ua ) {
	$ua_lc    = strtolower( (string) $ua );
	$best     = null;
	$best_len = -1;

	foreach ( $groups as $group ) {
		foreach ( $group['uas'] as $token ) {
			$tok = strtolower( trim( (string) $token ) );
			if ( '' === $tok ) {
				continue;
			}
			if ( '*' === $tok ) {
				if ( $best_len < 1 ) {
					$best     = $group;
					$best_len = 1;
				}
				continue;
			}
			if ( false !== strpos( $ua_lc, $tok ) ) {
				$len = strlen( $tok );
				if ( $len > $best_len ) {
					$best     = $group;
					$best_len = $len;
				}
			}
		}
	}

	return $best;
}

function airai_robots_rule_match_len( $pattern, $path ) {
	$pattern = (string) $pattern;
	$path    = (string) $path;

	if ( '' === $pattern ) {
		return 0;
	}

	$escaped = preg_quote( $pattern, '/' );
	$escaped = str_replace( '\\*', '.*', $escaped );
	$has_end = false;

	if ( substr( $escaped, -2 ) === '\\$' ) {
		$escaped = substr( $escaped, 0, -2 );
		$has_end = true;
	}

	$regex = '/^' . $escaped . ( $has_end ? '$' : '' ) . '/i';

	if ( preg_match( $regex, $path, $m ) ) {
		return strlen( (string) $m[0] );
	}

	return -1;
}

function airai_robots_is_allowed( $robots, $ua, $path ) {
	$groups = airai_robots_parse_groups( $robots );
	$group  = airai_robots_best_group_for_ua( $groups, $ua );

	if ( ! $group ) {
		return null;
	}

	$best_type = null;
	$best_len  = -1;

	foreach ( $group['rules'] as $rule ) {
		$len = airai_robots_rule_match_len( $rule['pattern'], $path );
		if ( $len > $best_len ) {
			$best_len  = $len;
			$best_type = $rule['type'];
		} elseif ( $len === $best_len && 'allow' === $rule['type'] ) {
			$best_type = 'allow';
		}
	}

	if ( $best_len < 0 ) {
		return null;
	}

	return 'allow' === $best_type;
}

function airai_get_served_robots() {
	$physical = false;
	$path     = ABSPATH . 'robots.txt';
	$robots   = '';

	if ( file_exists( $path ) && is_readable( $path ) ) {
		$size = filesize( $path );
		if ( is_int( $size ) && $size > 0 && $size <= 1024 * 1024 ) {
			$contents = file_get_contents( $path );
			if ( is_string( $contents ) ) {
				$robots   = $contents;
				$physical = true;
			}
		}
	}

	if ( '' === $robots ) {
		$public = '0' !== (string) get_option( 'blog_public', '1' );
		$base   = $public ? "User-agent: *\nDisallow:\n" : "User-agent: *\nDisallow: /\n";
		$robots = (string) apply_filters( 'robots_txt', $base, $public );
	}

	$robots = trim( str_replace( array( "\r\n", "\r" ), "\n", $robots ) );

	return array(
		'content'  => $robots,
		'physical' => $physical,
		'dynamic'  => ! $physical,
		'code'     => 200,
	);
}

function airai_get_sitemap_summary() {
	$wp_url  = home_url( '/wp-sitemap.xml' );
	$xml_url = home_url( '/sitemap.xml' );

	return array(
		'wp' => array(
			'label'  => 'WordPress sitemap',
			'url'    => esc_url_raw( $wp_url ),
			'status' => function_exists( 'wp_sitemaps_get_server' ) ? 200 : 0,
			'found'  => function_exists( 'wp_sitemaps_get_server' ),
		),
		'generic' => array(
			'label'  => 'Generic sitemap path',
			'url'    => esc_url_raw( $xml_url ),
			'status' => 0,
			'found'  => false,
		),
	);
}

function airai_get_homepage_signals() {
	$public = '0' !== (string) get_option( 'blog_public', '1' );

	return array(
		'url'        => esc_url_raw( home_url( '/' ) ),
		'status'     => 200,
		'metaRobots' => $public ? 'index, follow' : 'noindex, nofollow',
		'xRobotsTag' => '',
		'canonical'  => esc_url_raw( home_url( '/' ) ),
		'jsonLd'     => false,
		'discourage' => ! $public,
	);
}

function airai_get_important_urls() {
	$urls   = array();
	$urls[] = home_url( '/' );

	$front = (int) get_option( 'page_on_front' );
	if ( $front > 0 ) {
		$front_url = get_permalink( $front );
		if ( $front_url ) {
			$urls[] = $front_url;
		}
	}

	$recent = get_posts(
		array(
			'post_type'           => array( 'post', 'page' ),
			'post_status'         => 'publish',
			'posts_per_page'      => 8,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'suppress_filters'    => true,
			'ignore_sticky_posts' => true,
		)
	);

	foreach ( $recent as $post ) {
		$link = get_permalink( $post );
		if ( $link ) {
			$urls[] = $link;
		}
	}

	$urls[] = home_url( '/wp-sitemap.xml' );

	$urls = array_values( array_unique( array_filter( array_map( 'esc_url_raw', $urls ) ) ) );
	return array_slice( $urls, 0, 10 );
}

function airai_path_from_url( $url ) {
	$path = wp_parse_url( $url, PHP_URL_PATH );
	if ( ! is_string( $path ) || '' === $path ) {
		$path = '/';
	}
	return $path;
}

function airai_get_verification_rows( $robots ) {
	$rows = array();
	foreach ( airai_known_agents() as $agent ) {
		$rows[] = array(
			'ua'      => $agent['token'],
			'allowed' => airai_robots_is_allowed( $robots, $agent['token'], '/' ),
		);
	}
	return $rows;
}

function airai_get_crawl_matrix( $robots, $urls ) {
	$focus_tokens = array(
		'OAI-SearchBot',
		'ChatGPT-User',
		'GPTBot',
		'CCBot',
		'Google-Extended',
		'Applebot-Extended',
	);

	$rows = array();
	foreach ( $urls as $url ) {
		$path  = airai_path_from_url( $url );
		$rules = array();
		foreach ( $focus_tokens as $token ) {
			$rules[ $token ] = airai_robots_is_allowed( $robots, $token, $path );
		}
		$rows[] = array(
			'url'   => esc_url_raw( $url ),
			'rules' => $rules,
		);
	}
	return $rows;
}

function airai_get_policy_templates() {
	return array(
		array(
			'slug'        => 'balanced',
			'label'       => 'Balanced',
			'description' => 'Allow search and user-triggered fetches while blocking GPTBot training access.',
			'content'     => "User-agent: OAI-SearchBot\nAllow: /\n\nUser-agent: ChatGPT-User\nAllow: /\n\nUser-agent: GPTBot\nDisallow: /\n",
		),
		array(
			'slug'        => 'open',
			'label'       => 'Open',
			'description' => 'Allow all listed AI-related actors.',
			'content'     => "User-agent: *\nAllow: /\n",
		),
		array(
			'slug'        => 'user-fetch-only',
			'label'       => 'User fetch only',
			'description' => 'Allow direct user-triggered fetches and block major crawler tokens.',
			'content'     => "User-agent: ChatGPT-User\nAllow: /\n\nUser-agent: OAI-SearchBot\nDisallow: /\n\nUser-agent: GPTBot\nDisallow: /\n\nUser-agent: CCBot\nDisallow: /\n",
		),
		array(
			'slug'        => 'locked-down',
			'label'       => 'Locked down',
			'description' => 'Block all listed AI-related actors.',
			'content'     => "User-agent: OAI-SearchBot\nDisallow: /\n\nUser-agent: ChatGPT-User\nDisallow: /\n\nUser-agent: GPTBot\nDisallow: /\n\nUser-agent: CCBot\nDisallow: /\n\nUser-agent: Google-Extended\nDisallow: /\n\nUser-agent: Applebot-Extended\nDisallow: /\n",
		),
	);
}

function airai_calculate_readiness( $robots_info, $sitemap, $verification ) {
	$score  = 0;
	$checks = array();

	if ( 200 === (int) $robots_info['code'] ) {
		$score += 25;
		$checks[] = array( 'label' => 'robots.txt is available.' );
	}

	if ( ! empty( $robots_info['content'] ) ) {
		$score += 20;
		$checks[] = array( 'label' => 'robots.txt content is present.' );
	}

	if ( ! empty( $sitemap['wp']['found'] ) ) {
		$score += 20;
		$checks[] = array( 'label' => 'WordPress sitemap support is available.' );
	}

	$specified = 0;
	foreach ( $verification as $row ) {
		if ( null !== $row['allowed'] ) {
			$specified++;
		}
	}

	if ( $specified > 0 ) {
		$score += 20;
		$checks[] = array( 'label' => 'Specific AI agent rules were detected in robots.txt.' );
	}

	if ( ! empty( $robots_info['physical'] ) || ! empty( $robots_info['dynamic'] ) ) {
		$score += 15;
		$checks[] = array( 'label' => 'A robots.txt source was identified.' );
	}

	if ( $score > 100 ) {
		$score = 100;
	}

	$summary = $score >= 80
		? 'Strong baseline. Your site exposes the main signals needed for AI crawl policy review.'
		: ( $score >= 50
			? 'Moderate baseline. The core pieces are present, but there is room to improve clarity and coverage.'
			: 'Early baseline. Add or refine robots.txt and sitemap signals to improve discoverability and control.' );

	return array(
		'score'   => $score,
		'summary' => $summary,
		'checks'  => $checks,
	);
}

function airai_get_log() {
	$log = get_option( AIRAI_LOG_KEY, array() );
	return is_array( $log ) ? $log : array();
}

function airai_save_log( $rows ) {
	update_option( AIRAI_LOG_KEY, array_values( $rows ), false );
}

function airai_append_log( $entry ) {
	$opts  = airai_get_options();
	$rows  = airai_get_log();
	$rows[] = $entry;
	$limit = max( 50, (int) $opts['log_limit'] );
	if ( count( $rows ) > $limit ) {
		$rows = array_slice( $rows, -1 * $limit );
	}
	airai_save_log( $rows );
}

function airai_log_request( $forced_ua = '', $forced_path = '' ) {
	$opts = airai_get_options();
	if ( empty( $opts['enable_bot_logging'] ) ) {
		return;
	}

	$ua   = $forced_ua ? sanitize_text_field( $forced_ua ) : airai_server_http_user_agent();
	$path = $forced_path ? airai_sanitize_path( $forced_path ) : airai_server_request_uri();
	$bot  = 'Unknown';

	foreach ( airai_known_agents() as $agent ) {
		if ( false !== stripos( $ua, $agent['token'] ) ) {
			$bot = $agent['token'];
			break;
		}
	}

	$entry = array(
		't'    => current_time( 'mysql' ),
		'bot'  => $bot,
		'ua'   => $ua,
		'ip'   => airai_server_remote_addr(),
		'uri'  => $path,
		'host' => airai_server_http_host(),
	);

	airai_append_log( $entry );
}

function airai_collect_fast_state() {
	$opts = airai_get_options();

	return array(
		'version'        => AIRAI_VERSION,
		'playgroundMode' => airai_is_playground(),
		'theme'          => sanitize_text_field( $opts['ui_theme'] ),
		'knownActors'    => array(
			'crawlers'     => airai_robot_agents_by_type( 'crawler' ),
			'fetchers'     => airai_robot_agents_by_type( 'fetcher' ),
			'policyTokens' => airai_robot_agents_by_type( 'policy' ),
		),
	);
}

function airai_collect_dashboard_state() {
	$robots       = airai_get_served_robots();
	$sitemap      = airai_get_sitemap_summary();
	$homepage     = airai_get_homepage_signals();
	$verification = airai_get_verification_rows( $robots['content'] );
	$readiness    = airai_calculate_readiness( $robots, $sitemap, $verification );

	return array(
		'readiness'           => $readiness,
		'servedCode'          => (int) $robots['code'],
		'robotsPhysical'      => ! empty( $robots['physical'] ),
		'robotsDynamicLikely' => ! empty( $robots['dynamic'] ),
		'servedRobots'        => $robots['content'],
		'robotsHead'          => substr( $robots['content'], 0, 400 ),
		'sitemap'             => $sitemap,
		'homepage'            => $homepage,
		'verification'        => $verification,
	);
}

function airai_collect_audit_state() {
	$robots   = airai_get_served_robots();
	$urls     = airai_get_important_urls();
	$matrix   = airai_get_crawl_matrix( $robots['content'], $urls );
	$policies = airai_get_policy_templates();

	return array(
		'importantUrls'   => $urls,
		'crawlMatrix'     => $matrix,
		'policyTemplates' => $policies,
	);
}

function airai_require_ajax_permissions() {
	check_ajax_referer( 'airai_ajax' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error(
			array(
				'message' => 'Insufficient permissions.',
			),
			403
		);
	}
}

function airai_ajax_get_state() {
	airai_require_ajax_permissions();
	wp_send_json_success( airai_collect_fast_state() );
}
add_action( 'wp_ajax_airai_get_state', 'airai_ajax_get_state' );

function airai_ajax_get_dashboard_data() {
	airai_require_ajax_permissions();
	wp_send_json_success( airai_collect_dashboard_state() );
}
add_action( 'wp_ajax_airai_get_dashboard_data', 'airai_ajax_get_dashboard_data' );

function airai_ajax_get_audit_data() {
	airai_require_ajax_permissions();
	wp_send_json_success( airai_collect_audit_state() );
}
add_action( 'wp_ajax_airai_get_audit_data', 'airai_ajax_get_audit_data' );

function airai_ajax_get_logs() {
	airai_require_ajax_permissions();
	wp_send_json_success( array( 'log' => airai_get_log() ) );
}
add_action( 'wp_ajax_airai_get_logs', 'airai_ajax_get_logs' );

function airai_ajax_clear_logs() {
	airai_require_ajax_permissions();
	airai_save_log( array() );
	wp_send_json_success( array( 'cleared' => true ) );
}
add_action( 'wp_ajax_airai_clear_logs', 'airai_ajax_clear_logs' );

function airai_ajax_quick_test() {
	airai_require_ajax_permissions();
	airai_log_request( 'ChatGPT-User', '/' );
	wp_send_json_success(
		array(
			'message' => 'Quick test complete.',
		)
	);
}
add_action( 'wp_ajax_airai_run_quick_test', 'airai_ajax_quick_test' );

function airai_ajax_verify_custom() {
	airai_require_ajax_permissions();

	$ua   = isset( $_POST['ua'] ) ? sanitize_text_field( wp_unslash( $_POST['ua'] ) ) : '';
	$path = isset( $_POST['path'] ) ? airai_sanitize_path( $_POST['path'] ) : '/';

	$robots = airai_get_served_robots();

	wp_send_json_success(
		array(
			'allowed' => airai_robots_is_allowed( $robots['content'], $ua, $path ),
			'ua'      => $ua,
			'path'    => $path,
		)
	);
}
add_action( 'wp_ajax_airai_verify_custom', 'airai_ajax_verify_custom' );

function airai_ajax_generate_policy() {
	airai_require_ajax_permissions();

	$template = isset( $_POST['template'] ) ? sanitize_key( wp_unslash( $_POST['template'] ) ) : 'balanced';
	$selected = '';

	foreach ( airai_get_policy_templates() as $item ) {
		if ( $item['slug'] === $template ) {
			$selected = $item['content'];
			break;
		}
	}

	if ( '' === $selected ) {
		$templates = airai_get_policy_templates();
		$selected  = $templates[0]['content'];
	}

	wp_send_json_success(
		array(
			'content' => $selected,
		)
	);
}
add_action( 'wp_ajax_airai_generate_policy', 'airai_ajax_generate_policy' );

function airai_download_sample_robots() {
	airai_require_ajax_permissions();

	$template = isset( $_GET['template'] ) ? sanitize_key( wp_unslash( $_GET['template'] ) ) : 'balanced';
	$content  = '';
	foreach ( airai_get_policy_templates() as $item ) {
		if ( $item['slug'] === $template ) {
			$content = $item['content'];
			break;
		}
	}
	if ( '' === $content ) {
		$templates = airai_get_policy_templates();
		$content   = $templates[0]['content'];
	}

	nocache_headers();
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="robots-sample.txt"' );
	echo wp_kses_post( $content );
	exit;
}
add_action( 'wp_ajax_airai_download_sample_robots', 'airai_download_sample_robots' );

function airai_ping_rest_handler( WP_REST_Request $request ) {
	$path = airai_sanitize_path( $request->get_param( 'path' ) );
	airai_log_request( airai_server_http_user_agent(), $path );

	return rest_ensure_response(
		array(
			'ok'   => true,
			'path' => $path,
		)
	);
}

add_action(
	'rest_api_init',
	function() {
		register_rest_route(
			'airai/v1',
			'/ping',
			array(
				'methods'             => 'GET',
				'callback'            => 'airai_ping_rest_handler',
				'permission_callback' => '__return_true',
				'args'                => array(
					'path' => array(
						'required'          => false,
						'sanitize_callback' => 'airai_sanitize_path',
					),
				),
			)
		);
	}
);

function airai_add_menu_pages() {
	$cap      = 'manage_options';
	$icon     = 'dashicons-shield-alt';
	$position = 65;

	add_menu_page(
		__( 'AI Readiness', 'ai-readiness-advisor' ),
		__( 'AI Readiness', 'ai-readiness-advisor' ),
		$cap,
		'airai-dashboard',
		'airai_render_admin_page',
		$icon,
		$position
	);

	add_submenu_page(
		'airai-dashboard',
		__( 'Dashboard', 'ai-readiness-advisor' ),
		__( 'Dashboard', 'ai-readiness-advisor' ),
		$cap,
		'airai-dashboard',
		'airai_render_admin_page'
	);

	add_submenu_page(
		'airai-dashboard',
		__( 'Verification', 'ai-readiness-advisor' ),
		__( 'Verification', 'ai-readiness-advisor' ),
		$cap,
		'airai-verification',
		'airai_render_admin_page'
	);

	add_submenu_page(
		'airai-dashboard',
		__( 'Audit', 'ai-readiness-advisor' ),
		__( 'Audit', 'ai-readiness-advisor' ),
		$cap,
		'airai-audit',
		'airai_render_admin_page'
	);

	add_submenu_page(
		'airai-dashboard',
		__( 'Policies', 'ai-readiness-advisor' ),
		__( 'Policies', 'ai-readiness-advisor' ),
		$cap,
		'airai-policies',
		'airai_render_admin_page'
	);

	add_submenu_page(
		'airai-dashboard',
		__( 'Tools', 'ai-readiness-advisor' ),
		__( 'Tools', 'ai-readiness-advisor' ),
		$cap,
		'airai-tools',
		'airai_render_admin_page'
	);

	add_submenu_page(
		'airai-dashboard',
		__( 'Logs', 'ai-readiness-advisor' ),
		__( 'Logs', 'ai-readiness-advisor' ),
		$cap,
		'airai-logs',
		'airai_render_admin_page'
	);

	add_submenu_page(
		'airai-dashboard',
		__( 'Help', 'ai-readiness-advisor' ),
		__( 'Help', 'ai-readiness-advisor' ),
		$cap,
		'airai-help',
		'airai_render_admin_page'
	);

	add_submenu_page(
		'airai-dashboard',
		__( 'Setup Wizard', 'ai-readiness-advisor' ),
		__( 'Setup Wizard', 'ai-readiness-advisor' ),
		$cap,
		'airai-wizard',
		'airai_render_wizard_page'
	);
}
add_action( 'admin_menu', 'airai_add_menu_pages' );

function airai_render_admin_page() {
	?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'AI Readiness', 'ai-readiness-advisor' ); ?></h1>
		<div id="airai-app"></div>
	</div>
	<?php
}

function airai_render_wizard_page() {
	?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'AI Readiness Setup Wizard', 'ai-readiness-advisor' ); ?></h1>
		<p><?php echo esc_html__( 'This wizard helps you understand your site posture, choose your goals, and apply a recommended AI access policy.', 'ai-readiness-advisor' ); ?></p>
		<div id="airai-wizard-app"></div>
	</div>
	<?php
}

function airai_enqueue_admin_assets( $hook ) {
	if ( false === strpos( (string) $hook, 'airai' ) ) {
		return;
	}

	wp_enqueue_style(
		'airai-admin',
		plugin_dir_url( __FILE__ ) . 'assets/admin.css',
		array(),
		AIRAI_VERSION
	);

	wp_enqueue_script(
		'airai-admin',
		plugin_dir_url( __FILE__ ) . 'assets/admin.js',
		array(),
		AIRAI_VERSION,
		true
	);

	$opts = airai_get_options();

	wp_localize_script(
		'airai-admin',
		'AIRAI',
		array(
			'ajaxurl'       => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( 'airai_ajax' ),
			'currentPage'   => isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'airai-dashboard',
			'home'          => home_url( '/' ),
			'site'          => site_url( '/' ),
			'pluginVersion' => AIRAI_VERSION,
			'theme'         => sanitize_text_field( $opts['ui_theme'] ),
			'playground'    => airai_is_playground(),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'airai_enqueue_admin_assets' );
