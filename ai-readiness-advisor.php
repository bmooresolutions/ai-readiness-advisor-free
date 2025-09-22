<?php
/**
 * Plugin Name: AI Readiness Advisor
 * Plugin URI: https://www.bmooresolutions.com/tools/ai-readiness-advisor
 * Description: Audit & verify AI-crawler access, log known AI user-agents, Quick Test tool (no shell), robots.txt status, and Help page with copyable commands.
 * Version: 1.5.6
 * Requires at least: 5.8
 * Tested up to: 6.6
 * Requires PHP: 7.4
 * Author: BMoore Solutions
 * Author URI: https://bmooresolutions.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-readiness-advisor
 * Tags: robots, crawl, audit, privacy, logging
 */


if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'AIRAI_FREE_OPT_KEY', 'airai_free_options_v2' );
define( 'AIRAI_FREE_LOG_KEY', 'airai_free_bot_log_v1' );

/** Defaults + options */
function airai_free_default_options() {
	return array(
		'enable_bot_logging' => true,
		'log_limit'          => 500,
		'ui_theme'           => 'default',
	);
}
function airai_free_get_options() {
	$opts = get_option( AIRAI_FREE_OPT_KEY );
	if ( ! is_array( $opts ) ) { $opts = array(); }
	return array_merge( airai_free_default_options(), $opts );
}
register_activation_hook( __FILE__, function(){
	if ( ! get_option( AIRAI_FREE_OPT_KEY ) ) {
		add_option( AIRAI_FREE_OPT_KEY, airai_free_default_options() );
	}
} );

/** Explicit server accessors (unslash+sanitize on the *same* line, PCP-friendly) */
function airai_server_http_user_agent() { return sanitize_text_field( wp_unslash( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ) ); }
function airai_server_remote_addr()     { return sanitize_text_field( wp_unslash( (string) ( $_SERVER['REMOTE_ADDR']     ?? '' ) ) ); }
function airai_server_request_uri()     { return sanitize_text_field( wp_unslash( (string) ( $_SERVER['REQUEST_URI']     ?? '' ) ) ); }
function airai_server_http_host()       { return sanitize_text_field( wp_unslash( (string) ( $_SERVER['HTTP_HOST']       ?? '' ) ) ); }
function airai_server_server_addr()     { return sanitize_text_field( wp_unslash( (string) ( $_SERVER['SERVER_ADDR']     ?? '' ) ) ); }
function airai_server_server_software() { return sanitize_text_field( wp_unslash( (string) ( $_SERVER['SERVER_SOFTWARE'] ?? '' ) ) ); }

/** Path sanitizer (includes normalization + final sanitize) */
function airai_sanitize_path( $raw ) {
	$val = is_string( $raw ) ? $raw : '/';
	$val = wp_unslash( $val );
	$val = preg_replace( '~[^A-Za-z0-9\-\._~!\$&\'\(\)\*\+,;=:@%/]+~', '', $val );
	if ( $val === '' ) { $val = '/'; }
	if ( $val[0] !== '/' ) { $val = '/' . $val; }
	if ( strlen( $val ) > 1024 ) { $val = substr( $val, 0, 1024 ); }
	return sanitize_text_field( $val );
}

/** Known UAs */
function airai_known_uas() {
	return array(
		'OAI-SearchBot'      => 'OAI-SearchBot',
		'ChatGPT-User'       => 'ChatGPT-User',
		'PerplexityBot'      => 'PerplexityBot',
		'GPTBot'             => 'GPTBot',
		'Google-Extended'    => 'Google-Extended',
		'Applebot-Extended'  => 'Applebot-Extended',
		'CCBot'              => 'CCBot',
	);
}

/** Robots.txt parser and evaluator */
function airai_robots_parse_groups( $robots ) {
	$robots = str_replace( array( "\r\n", "\r" ), "\n", (string) $robots );
	$lines  = preg_split( '/\n+/', $robots );
	$groups = array();
	$current = array( 'uas' => array(), 'rules' => array() );
	$in = false;
	foreach ( $lines as $raw ) {
		$line = trim( $raw );
		if ( $line === '' || strpos( $line, '#' ) === 0 ) {
			if ( $in ) { $groups[] = $current; $current = array( 'uas' => array(), 'rules' => array() ); $in = false; }
			continue;
		}
		$parts = explode( ':', $line, 2 );
		if ( count( $parts ) < 2 ) { continue; }
		$field = strtolower( trim( $parts[0] ) );
		$value = trim( $parts[1] );
		if ( $field === 'user-agent' ) {
			if ( $in && ( ! empty( $current['uas'] ) || ! empty( $current['rules'] ) ) ) { $groups[] = $current; $current = array( 'uas' => array(), 'rules' => array() ); }
			$in = true; $current['uas'][] = $value;
		} elseif ( $field === 'disallow' || $field === 'allow' ) {
			if ( ! $in ) { $current = array( 'uas' => array( '*' ), 'rules' => array() ); $in = true; }
			$current['rules'][] = array( 'type' => $field, 'pattern' => $value );
		}
	}
	if ( $in ) { $groups[] = $current; }
	return $groups;
}
function airai_robots_best_group_for_ua( $groups, $ua ) {
	$ua_lc = strtolower( (string) $ua );
	$best = null; $best_len = -1;
	foreach ( $groups as $g ) {
		foreach ( $g['uas'] as $token ) {
			$tok = strtolower( $token );
			if ( $tok === '*' ) {
				if ( $best === null && $best_len < 1 ) { $best = $g; $best_len = 1; }
			} elseif ( $tok !== '' && strpos( $ua_lc, $tok ) !== false ) {
				$len = strlen( $tok );
				if ( $len > $best_len ) { $best = $g; $best_len = $len; }
			}
		}
	}
	return $best;
}
function airai_robots_rule_match_len( $pattern, $path ) {
	$pattern = (string) $pattern; $path = (string) $path;
	if ( $pattern === '' ) { return 0; }
	$escaped = preg_quote( $pattern, '/' );
	$escaped = str_replace( '\*', '.*', $escaped );
	$end = false;
	if ( substr( $escaped, -2 ) === '\$' ) { $escaped = substr( $escaped, 0, -2 ) . '$'; $end = true; }
	$regex = '/' . '^' . $escaped . '/' . 'i';
	if ( preg_match( $regex, $path, $m ) ) {
		$len = strlen( $m[0] ); return $end ? ( $len + 1 ) : $len;
	}
	return 0;
}
function airai_robots_allowed( $robots, $ua, $path ) {
	$groups = airai_robots_parse_groups( $robots );
	$group  = airai_robots_best_group_for_ua( $groups, $ua );
	if ( ! $group ) { return null; }
	$best_type = null; $best_len = -1;
	foreach ( $group['rules'] as $r ) {
		$pat = trim( $r['pattern'] );
		$ml  = ( $pat === '' ? 0 : airai_robots_rule_match_len( $pat, $path ) );
		if ( $ml > $best_len ) { $best_len = $ml; $best_type = $r['type']; }
		elseif ( $ml === $best_len && 'allow' !== $best_type && 'allow' === $r['type'] ) { $best_type = 'allow'; }
	}
	if ( $best_len < 0 ) { return true; }
	return ( 'allow' === $best_type );
}

/** Append a log entry (helper used by Quick Test) */
function airai_append_log_entry( $bot, $ua, $uri, $ip, $host ) {
	$log = get_option( AIRAI_FREE_LOG_KEY, array() );
	if ( ! is_array( $log ) ) { $log = array(); }
	$log[] = array(
		't'   => current_time( 'mysql' ),
		'ua'  => sanitize_text_field( $ua ),
		'bot' => sanitize_text_field( $bot ),
		'ip'  => sanitize_text_field( $ip ),
		'uri' => sanitize_text_field( $uri ),
		'host'=> sanitize_text_field( $host ),
	);
	$opt   = airai_free_get_options();
	$limit = (int) ( isset( $opt['log_limit'] ) ? $opt['log_limit'] : 500 );
	if ( $limit < 50 ) { $limit = 50; }
	if ( count( $log ) > $limit ) {
		$start = max( 0, count( $log ) - (int) $limit );
		$log   = array_slice( $log, $start );
	}
	update_option( AIRAI_FREE_LOG_KEY, $log, false );
}

/** Passive bot logger */
add_action( 'init', function(){
	$opt = airai_free_get_options();
	if ( empty( $opt['enable_bot_logging'] ) ) { return; }
	$ua_seen = airai_server_http_user_agent();
	if ( $ua_seen === '' ) { return; }
	$match = null;
	foreach ( array_keys( airai_known_uas() ) as $needle ) {
		if ( stripos( $ua_seen, $needle ) !== false ) { $match = $needle; break; }
	}
	if ( $match === null ) { return; }
	airai_append_log_entry( $match, $ua_seen, airai_server_request_uri(), airai_server_remote_addr(), airai_server_http_host() );
} );

/** Admin pages */
add_action( 'admin_menu', function(){
	add_menu_page( esc_html__( 'AI Readiness', 'ai-readiness-advisor' ), esc_html__( 'AI Readiness', 'ai-readiness-advisor' ), 'manage_options', 'airai-dashboard', 'airai_render_app', 'dashicons-shield', 58 );
	add_submenu_page( 'airai-dashboard', esc_html__( 'Verification', 'ai-readiness-advisor' ), esc_html__( 'Verification', 'ai-readiness-advisor' ), 'manage_options', 'airai-verify', 'airai_render_app' );
	add_submenu_page( 'airai-dashboard', esc_html__( 'Tools', 'ai-readiness-advisor' ), esc_html__( 'Tools', 'ai-readiness-advisor' ), 'manage_options', 'airai-tools', 'airai_render_app' );
	add_submenu_page( 'airai-dashboard', esc_html__( 'Logs', 'ai-readiness-advisor' ), esc_html__( 'Logs', 'ai-readiness-advisor' ), 'manage_options', 'airai-logs', 'airai_render_app' );
	add_submenu_page( 'airai-dashboard', esc_html__( 'Help', 'ai-readiness-advisor' ), esc_html__( 'Help', 'ai-readiness-advisor' ), 'manage_options', 'airai-help', 'airai_render_app' );
} );
add_action( 'admin_menu', function(){ remove_submenu_page( 'airai-dashboard', 'airai-dashboard' ); }, 100 );

/** Enqueue admin assets only on our pages */
add_action( 'admin_enqueue_scripts', function(){
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
	if ( strpos( $page, 'airai-' ) !== 0 ) { return; }
	$ver  = '1.5.5';
	$base = plugin_dir_url( __FILE__ ) . 'assets/';
	wp_enqueue_style( 'airai-free-admin', $base . 'admin.css', array(), $ver );
	wp_enqueue_script( 'airai-free-admin', $base . 'admin.js', array(), $ver, true );
	$opts = airai_free_get_options(); if ( ! is_array( $opts ) ) { $opts = array(); }
	$data = array(
		'ajaxurl'      => admin_url( 'admin-ajax.php' ),
		'nonce'        => wp_create_nonce( 'airai_free_ajax' ),
		'currentPage'  => $page === '' ? 'airai-dashboard' : $page,
		'home'         => home_url( '/' ),
		'site'         => site_url( '/' ),
		'pluginVersion'=> $ver,
		'theme'        => ( isset( $opts['ui_theme'] ) ? sanitize_text_field( $opts['ui_theme'] ) : 'default' ),
	);
	wp_localize_script( 'airai-free-admin', 'AIRAI_FREE', $data );
	wp_localize_script( 'airai-free-admin', 'AIRAI', $data ); // compatibility alias
} );

/** Render root container(s) */
function airai_render_app() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'ai-readiness-advisor' ) );
	}
	echo '<div class="wrap"><h1>' . esc_html__( 'AI Readiness', 'ai-readiness-advisor' ) . '</h1><div id="airai-free-app"></div><div id="airai-app"></div></div>';
}

/** AJAX — nonce-protected */
add_action( 'wp_ajax_airai_free_get_state', 'airai_free_ajax_get_state' );
function airai_collect_state() {
	$opt   = airai_free_get_options();
	$hostname = gethostname();
	$server_addr = airai_server_server_addr();
	if ( $server_addr === '' ) { $server_addr = gethostbyname( $hostname ); }
	$server_soft = airai_server_server_software();

	$robots = ''; $code = 0;
	$res = wp_remote_get( home_url( '/robots.txt' ), array( 'timeout' => 8 ) );
	if ( ! is_wp_error( $res ) ) {
		$code   = wp_remote_retrieve_response_code( $res );
		$robots = (string) wp_remote_retrieve_body( $res );
	}
	$physical = false;
	if ( file_exists( ABSPATH . 'wp-admin/includes/file.php' ) ) { require_once ABSPATH . 'wp-admin/includes/file.php'; }
	if ( function_exists( 'get_home_path' ) ) {
		$home_path = get_home_path();
		if ( is_string( $home_path ) ) {
			$robots_path = rtrim( $home_path, '/\\' ) . '/robots.txt';
			$physical = file_exists( $robots_path );
		}
	}
	$verif = array();
	foreach ( airai_known_uas() as $ua ) {
		$verif[] = array( 'ua' => $ua, 'allowed' => airai_robots_allowed( $robots, $ua, '/' ) );
	}
	$ld_ok = false;
	$html  = wp_remote_get( home_url( '/' ), array( 'timeout' => 6 ) );
	if ( ! is_wp_error( $html ) ) {
		$body = (string) wp_remote_retrieve_body( $html );
		if ( preg_match( '/application\/ld\+json/i', $body ) ) { $ld_ok = true; }
	}
	$ping_url = add_query_arg( array( 'path' => '/' ), home_url( '/wp-json/airai/v1/ping' ) );
	$ping_res = wp_remote_get( $ping_url, array( 'timeout' => 8, 'headers' => array( 'User-Agent' => 'OAI-SearchBot' ) ) );
	$ping_ok  = ( ! is_wp_error( $ping_res ) && wp_remote_retrieve_response_code( $ping_res ) === 200 );

	$logging_on = ! empty( $opt['enable_bot_logging'] );
	$dynamic_likely = ( $code === 200 && ! $physical );

	$score = 0;
	if ( $code === 200 ) { $score += 30; }
	if ( $physical ) { $score += 25; }
	if ( ! empty( $verif ) ) { $score += 15; }
	if ( $ping_ok ) { $score += 15; }
	if ( $ld_ok ) { $score += 10; }
	if ( $logging_on ) { $score += 5; }
	if ( $score > 100 ) { $score = 100; }

	$robots_head = implode( "\n", array_slice( explode( "\n", (string) $robots ), 0, 12 ) );

	return array(
		'options' => $opt,
		'env'     => array(
			'hostname' => $hostname,
			'server_ip'=> $server_addr,
			'server'   => $server_soft,
			'php'      => PHP_VERSION,
			'wp'       => get_bloginfo( 'version' ),
			'home'     => home_url( '/' ),
			'site'     => site_url( '/' ),
		),
		'servedRobots' => $robots,
		'servedCode'   => $code,
		'robotsHead'   => $robots_head,
		'robotsPhysical'=> $physical,
		'robotsDynamicLikely' => $dynamic_likely,
		'verification' => $verif,
		'readiness'    => array( 'score' => $score ),
	);
}
function airai_free_ajax_get_state() {
	check_ajax_referer( 'airai_free_ajax' );
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'forbidden' ), 403 ); }
	wp_send_json_success( airai_collect_state() );
}
add_action( 'wp_ajax_airai_free_get_logs', 'airai_ajax_get_logs' );
function airai_ajax_get_logs() {
	check_ajax_referer( 'airai_free_ajax' );
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'forbidden' ), 403 ); }
	$log = get_option( AIRAI_FREE_LOG_KEY, array() );
	if ( ! is_array( $log ) ) { $log = array(); }
	wp_send_json_success( array( 'log' => $log ) );
}
add_action( 'wp_ajax_airai_free_clear_logs', 'airai_ajax_clear_logs' );
function airai_ajax_clear_logs() {
	check_ajax_referer( 'airai_free_ajax' );
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'forbidden' ), 403 ); }
	update_option( AIRAI_FREE_LOG_KEY, array(), false );
	wp_send_json_success( array( 'ok' => true ) );
}
add_action( 'wp_ajax_airai_free_run_quick_test', 'airai_ajax_quick_test' );
function airai_ajax_quick_test() {
	check_ajax_referer( 'airai_free_ajax' );
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'forbidden' ), 403 ); }
	$ua   = 'ChatGPT-User';
	$path = '/airai-test';
	$ping_url = add_query_arg( array( 'path' => $path ), home_url( '/wp-json/airai/v1/ping' ) );
	$res  = wp_remote_get( $ping_url, array( 'timeout' => 12, 'headers' => array( 'User-Agent' => $ua ) ) );
	$code = is_wp_error( $res ) ? 0 : wp_remote_retrieve_response_code( $res );

	// Ensure a visible log entry even if loopback didn't hit the passive logger
	airai_append_log_entry( 'ChatGPT-User', $ua, '/airai-quicktest', airai_server_remote_addr(), airai_server_http_host() );

	wp_send_json_success( array( 'http' => $code, 'url' => $ping_url, 'ua' => $ua ) );
}
add_action( 'wp_ajax_airai_free_verify_custom', 'airai_ajax_verify_custom' );
function airai_ajax_verify_custom() {
	check_ajax_referer( 'airai_free_ajax' );
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'forbidden' ), 403 ); }
	$ua   = isset( $_POST['ua'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['ua'] ) ) : 'OAI-SearchBot';
	if ( ! isset( airai_known_uas()[ $ua ] ) ) { $ua = 'OAI-SearchBot'; }
	$path = '/';
	if ( isset( $_POST['path'] ) ) {
		// sanitize on the same line where the superglobal is used (WPCS-friendly)
		$path = airai_sanitize_path( sanitize_text_field( wp_unslash( (string) $_POST['path'] ) ) );
	}
	$res  = wp_remote_get( home_url( '/robots.txt' ), array( 'timeout' => 8 ) );
	$robots = ( ! is_wp_error( $res ) ) ? (string) wp_remote_retrieve_body( $res ) : '';
	$allowed = airai_robots_allowed( $robots, (string) $ua, $path );
	wp_send_json_success( array( 'ua' => $ua, 'path' => $path, 'allowed' => $allowed ) );
}

/** REST ping — intentionally public for testing */
add_action( 'rest_api_init', function(){
	register_rest_route( 'airai/v1', '/ping', array(
		'methods'  => 'GET',
		'callback' => 'airai_rest_ping',
		'permission_callback' => '__return_true',
		'args' => array(
			'path' => array( 'required' => false, 'sanitize_callback' => 'airai_sanitize_path' ),
			'ua'   => array( 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ),
		),
	) );
} );
function airai_rest_ping( $request ) {
	$req_path = $request->get_param( 'path' );
	$req_ua   = $request->get_param( 'ua' );
	$ua_seen  = airai_server_http_user_agent();
	$ua_eff   = ( is_string( $req_ua ) && $req_ua !== '' ) ? sanitize_text_field( $req_ua ) : $ua_seen;
	if ( strlen( $ua_eff ) > 512 ) { $ua_eff = substr( $ua_eff, 0, 512 ); }
	$path     = airai_sanitize_path( $req_path );
	$robots   = '';
	$res      = wp_remote_get( home_url( '/robots.txt' ), array( 'timeout' => 8 ) );
	if ( ! is_wp_error( $res ) ) { $robots = (string) wp_remote_retrieve_body( $res ); }
	$allowed  = airai_robots_allowed( $robots, (string) $ua_eff, $path );
	return rest_ensure_response( array(
		'time'        => current_time( 'mysql' ),
		'ip'          => airai_server_remote_addr(),
		'host'        => airai_server_http_host(),
		'ua_seen'     => $ua_seen,
		'ua_param'    => is_string( $req_ua ) ? sanitize_text_field( $req_ua ) : null,
		'ua_effective'=> $ua_eff,
		'path'        => $path,
		'allowed'     => $allowed,
		'robots_head' => substr( (string) $robots, 0, 2000 ),
	) );
}

/** AJAX: download sample robots.txt */
add_action( 'wp_ajax_airai_free_download_sample_robots', function(){
	check_ajax_referer( 'airai_free_ajax' );
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'forbidden' ); }
	$sample = "User-agent: *\nDisallow:\n\n# Examples for specific bots\nUser-agent: GPTBot\nDisallow: /\n";
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=\"robots.txt\"' );
	echo $sample; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit;
} );
