<?php
/**
 * Plugin Name: AI Readiness Advisor
 * Plugin URI: https://bmooresolutions.com/ai-readiness-advisor
 * Description: Audit and verify AI-crawler access, log known AI user-agents, Quick Test tool (no shell), robots.txt status, readiness score, Help with server snippets.
 * Version: 1.4.2
 * Requires at least: 5.4
 * Tested up to: 6.8.2
 * Requires PHP: 7.2
 * Author: AI Readiness Team
 * Author URI: https://bmooresolutions.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-readiness-advisor
 */

namespace AIRAIFREE;
if (!defined('ABSPATH')) { exit; }

define('AIRAI_FREE_OPT_KEY', 'airai_free_options_v1');
define('AIRAI_FREE_LOG_KEY', 'airai_free_bot_log_v1');

function airai_free_default_options() { return array('enable_bot_logging'=>true,'log_limit'=>300,'ui_theme'=>'default'); }
function airai_free_get_options() { $o=get_option(AIRAI_FREE_OPT_KEY); if(!is_array($o)) $o=array(); return array_merge(airai_free_default_options(), $o); }
\register_activation_hook(__FILE__, function(){ if(!get_option(AIRAI_FREE_OPT_KEY)) add_option(AIRAI_FREE_OPT_KEY, airai_free_default_options()); });

function airai_known_ai_uas() {
    return array(
        'OAI-SearchBot'     => array('ua'=>'OAI-SearchBot','desc'=>esc_html__('OpenAI search discovery (AI search previews, not training)','ai-readiness-advisor')),
        'ChatGPT-User'      => array('ua'=>'ChatGPT-User','desc'=>esc_html__('Fetcher when a ChatGPT user opens your link (not training)','ai-readiness-advisor')),
        'PerplexityBot'     => array('ua'=>'PerplexityBot','desc'=>esc_html__('Perplexity AI web crawler for answers','ai-readiness-advisor')),
        'GPTBot'            => array('ua'=>'GPTBot','desc'=>esc_html__('OpenAI web crawler for model training','ai-readiness-advisor')),
        'Google-Extended'   => array('ua'=>'Google-Extended','desc'=>esc_html__('Google AI data usage control token','ai-readiness-advisor')),
        'Applebot-Extended' => array('ua'=>'Applebot-Extended','desc'=>esc_html__('Apple extended AI usage control token','ai-readiness-advisor')),
        'CCBot'             => array('ua'=>'CCBot','desc'=>esc_html__('Common Crawl (public corpus used by many models)','ai-readiness-advisor')),
    );
}

/** robots.txt helpers (ASCII-only) */
function airai_robots_parse_groups($robots) {
    $robots = str_replace(array("\r\n","\r"), "\n", (string)$robots);
    $lines = preg_split('/\n+/', $robots);
    $groups = array(); $current = array('uas'=>array(),'rules'=>array()); $in=false;
    foreach($lines as $raw){
        $line = trim($raw);
        if ($line==='' || strpos($line,'#')===0){
            if($in){ $groups[]=$current; $current=array('uas'=>array(),'rules'=>array()); $in=false; }
            continue;
        }
        $parts = explode(':',$line,2); if(count($parts)<2) continue;
        $field = strtolower(trim($parts[0])); $value = trim($parts[1]);
        if($field==='user-agent'){
            if($in && (!empty($current['uas']) || !empty($current['rules']))){ $groups[]=$current; $current=array('uas'=>array(),'rules'=>array()); }
            $in=true; $current['uas'][]=$value;
        } elseif($field==='disallow' || $field==='allow'){
            if(!$in){ $current=array('uas'=>array('*'),'rules'=>array()); $in=true; }
            $current['rules'][]=array('type'=>$field,'pattern'=>$value);
        }
    }
    if($in){ $groups[]=$current; }
    return $groups;
}
function airai_robots_best_group_for_ua($groups,$ua){
    $ua_lc=strtolower((string)$ua); $best=null; $best_len=-1;
    foreach($groups as $g){ foreach($g['uas'] as $token){ $tok=strtolower($token);
        if($tok==='*'){ if($best===null && $best_len<1){ $best=$g; $best_len=1; } }
        elseif($tok!=='' && strpos($ua_lc,$tok)!==false){ $len=strlen($tok); if($len>$best_len){ $best=$g; $best_len=$len; } }
    }}
    return $best;
}
function airai_robots_rule_match_len($pattern,$path){
    $pattern=(string)$pattern; $path=(string)$path; if($pattern==='') return 0;
    $escaped=preg_quote($pattern,'/'); $escaped=str_replace('\*','.*',$escaped);
    $end=false; if(substr($escaped,-2)==='\$'){ $escaped=substr($escaped,0,-2).'$'; $end=true; }
    $regex='/' . '^' . $escaped . '/' . 'i';
    if(preg_match($regex,$path,$m)){ $len=strlen($m[0]); return $end?($len+1):$len; }
    return 0;
}
function airai_robots_allowed($robots,$ua,$path){
    $groups=airai_robots_parse_groups($robots); $group=airai_robots_best_group_for_ua($groups,$ua);
    if(!$group) return null;
    $best_type=null; $best_len=-1;
    foreach($group['rules'] as $r){
        $pat=trim($r['pattern']); $ml=($pat===''?0:airai_robots_rule_match_len($pat,$path));
        if($ml>$best_len){ $best_len=$ml; $best_type=$r['type']; }
        elseif($ml===$best_len && $best_type!=='allow' && $r['type']==='allow'){ $best_type='allow'; }
    }
    if($best_len<0) return true;
    return ($best_type==='allow');
}

/** logger */
\add_action('init', function(){
    $opts=airai_free_get_options(); if(empty($opts['enable_bot_logging'])) return;
    // Early sanitize to satisfy WP sniffers and safety
    $ua_seen = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field( \wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ) : '';
    if($ua_seen==='') return;
    $known=array_keys(airai_known_ai_uas()); $match=null; foreach($known as $needle){ if(stripos($ua_seen,$needle)!==false){ $match=$needle; break; } }
    if($match===null) return;
    $ua=$ua_seen;
    $ip=sanitize_text_field(isset($_SERVER['REMOTE_ADDR']) ? \wp_unslash((string)$_SERVER['REMOTE_ADDR']) : '');
    $uri=sanitize_text_field(isset($_SERVER['REQUEST_URI']) ? \wp_unslash((string)$_SERVER['REQUEST_URI']) : '');
    $host=sanitize_text_field(isset($_SERVER['HTTP_HOST']) ? \wp_unslash((string)$_SERVER['HTTP_HOST']) : '');
    $log=get_option(AIRAI_FREE_LOG_KEY,array()); if(!is_array($log)) $log=array();
    $log[] = array('t'=>current_time('mysql'),'ua'=>$ua,'bot'=>$match,'ip'=>$ip,'uri'=>$uri,'host'=>$host);
    $limit=intval(isset($opts['log_limit'])?$opts['log_limit']:300); if($limit<50) $limit=50;
    if(count($log)>$limit){ $start=max(0,count($log)-(int)$limit); $log=array_slice($log,$start); }
    update_option(AIRAI_FREE_LOG_KEY,$log,false);
});

/** admin pages */
\add_action('admin_menu', function(){
    \add_menu_page(esc_html__('AI Readiness','ai-readiness-advisor'), esc_html__('AI Readiness','ai-readiness-advisor'), 'manage_options', 'airai-free-dashboard', __NAMESPACE__.'\\airai_render_app', 'dashicons-shield', 59);
    \add_submenu_page('airai-free-dashboard', esc_html__('Verification','ai-readiness-advisor'), esc_html__('Verification','ai-readiness-advisor'), 'manage_options', 'airai-free-verify', __NAMESPACE__.'\\airai_render_app');
    \add_submenu_page('airai-free-dashboard', esc_html__('Logs','ai-readiness-advisor'), esc_html__('Logs','ai-readiness-advisor'), 'manage_options', 'airai-free-logs', __NAMESPACE__.'\\airai_render_app');
    \add_submenu_page('airai-free-dashboard', esc_html__('Tools','ai-readiness-advisor'), esc_html__('Tools','ai-readiness-advisor'), 'manage_options', 'airai-free-tools', __NAMESPACE__.'\\airai_render_app');
    \add_submenu_page('airai-free-dashboard', esc_html__('Help','ai-readiness-advisor'), esc_html__('Help','ai-readiness-advisor'), 'manage_options', 'airai-free-help', __NAMESPACE__.'\\airai_render_app');
}, 9);
\add_action('admin_menu', function(){ \remove_submenu_page('airai-free-dashboard','airai-free-dashboard'); }, 100);

\add_action('admin_enqueue_scripts', function(){
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $page = isset($_GET['page']) ? sanitize_text_field(\wp_unslash((string)$_GET['page'])) : '';
    if ($page !== 'airai-free-dashboard' && strpos($page, 'airai-free-') !== 0) return;
    $ver='1.4.2'; $base=plugin_dir_url(__FILE__).'assets/';
    \wp_enqueue_style('airai-free-admin', $base.'admin.css', array(), $ver);
    \wp_enqueue_script('airai-free-admin', $base.'admin.js', array(), $ver, true);
    $opts=airai_free_get_options();
    \wp_localize_script('airai-free-admin','AIRAI_FREE', array(
        'ajaxurl'=>\admin_url('admin-ajax.php'),
        'nonce'=>\wp_create_nonce('airai_free_ajax'),
        'theme'=>$opts['ui_theme'],
        'currentPage'=>($page===''?'airai-free-dashboard':$page),
        'home'=>\home_url('/'),
        'site'=>\site_url('/'),
        'pluginVersion'=>$ver
    ));
});

function airai_render_app(){
    if(!\current_user_can('manage_options')){ \wp_die(esc_html__('Insufficient permissions.','ai-readiness-advisor')); }
    echo '<div class="wrap"><h1>AI Readiness</h1><div id="airai-free-app"></div></div>';
}

/** AJAX endpoints */
\add_action('wp_ajax_airai_free_get_state', __NAMESPACE__.'\\airai_free_ajax_get_state');
\add_action('wp_ajax_airai_free_get_state_open', __NAMESPACE__.'\\airai_free_ajax_get_state_open'); // nonce-less fallback (still requires manage_options)

function airai_free_collect_state(){
    $opts=airai_free_get_options();
    $hostname=gethostname();
    $server_addr = isset($_SERVER['SERVER_ADDR']) ? sanitize_text_field(\wp_unslash((string)$_SERVER['SERVER_ADDR'])) : gethostbyname($hostname);
    $server_soft = isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(\wp_unslash((string)$_SERVER['SERVER_SOFTWARE'])) : '';
    $served=''; $served_code=0; $res=\wp_remote_get(\home_url('/robots.txt'), array('timeout'=>8));
    if(!\is_wp_error($res)){ $served_code=\wp_remote_retrieve_response_code($res); $served=(string)\wp_remote_retrieve_body($res); }
    $physical=false; if(file_exists(ABSPATH.'wp-admin/includes/file.php')) require_once ABSPATH.'wp-admin/includes/file.php';
    if(function_exists('get_home_path')){ $home_path=\get_home_path(); if(is_string($home_path)){ $robots_path=rtrim($home_path,'/\\').'/robots.txt'; $physical=file_exists($robots_path); } }
    $verif=array(); foreach(airai_known_ai_uas() as $meta){ $ua=$meta['ua']; $verif[]=array('ua'=>$ua,'allowed'=>airai_robots_allowed($served,$ua,'/')); }
    $ping_url=\add_query_arg(array('path'=>'/'), \home_url('/wp-json/airai/v1/ping'));
    $ping_res=\wp_remote_get($ping_url, array('timeout'=>8,'headers'=>array('User-Agent'=>'OAI-SearchBot'))); $ping_ok=(!\is_wp_error($ping_res) && \wp_remote_retrieve_response_code($ping_res)===200);
    $ld_ok=false; $html_res=\wp_remote_get(\home_url('/'), array('timeout'=>6)); if(!\is_wp_error($html_res)){ $html=(string)\wp_remote_retrieve_body($html_res); if(preg_match('/application\/ld\+json/i',$html)) $ld_ok=true; }
    $logging_on=!empty($opts['enable_bot_logging']); $robots_dynamic_likely=($served_code===200 && !$physical);
    $score=0; if($served_code===200) $score+=25; if($physical) $score+=20; if(!empty($verif)) $score+=15; if($ping_ok) $score+=15; if($ld_ok) $score+=15; if($logging_on) $score+=10; if($score>100) $score=100;
    return array(
        'options'=>$opts,'env'=>array('hostname'=>$hostname,'server_ip'=>$server_addr,'server'=>$server_soft,'php'=>PHP_VERSION,'wp'=>get_bloginfo('version'),'home'=>\home_url('/'),'site'=>\site_url('/')),
        'servedRobots'=>$served,'servedCode'=>$served_code,'robotsPhysical'=>$physical,'robotsDynamicLikely'=>$robots_dynamic_likely,
        'verification'=>$verif,'readiness'=>array('score'=>$score,'breakdown'=>array('robots_http_200'=>($served_code===200),'physical_robots'=>$physical,'robots_wp_dynamic'=>$robots_dynamic_likely,'ua_group_root'=>(!empty($verif)),'ping_endpoint'=>$ping_ok,'ld_json'=>$ld_ok,'logging_enabled'=>$logging_on)),
        'logsCount'=> (is_array(get_option(AIRAI_FREE_LOG_KEY,array()))? count(get_option(AIRAI_FREE_LOG_KEY,array())):0)
    );
}

function airai_free_ajax_get_state(){
    \check_ajax_referer('airai_free_ajax');
    if(!\current_user_can('manage_options')) \wp_send_json_error(array('message'=>esc_html__('forbidden','ai-readiness-advisor')),403);
    $data = airai_free_collect_state();
    \wp_send_json_success($data);
}

function airai_free_ajax_get_state_open(){
    // Fallback without nonce; still admin-only
    if(!\current_user_can('manage_options')) \wp_send_json_error(array('message'=>esc_html__('forbidden','ai-readiness-advisor')),403);
    $data = airai_free_collect_state();
    \wp_send_json_success($data);
}

\add_action('wp_ajax_airai_free_verify_custom', __NAMESPACE__.'\\airai_free_verify_custom');
function airai_free_verify_custom(){
    \check_ajax_referer('airai_free_ajax'); if(!\current_user_can('manage_options')) \wp_send_json_error(array('message'=>esc_html__('forbidden','ai-readiness-advisor')),403);
    $ua = isset($_POST['ua']) ? sanitize_text_field(\wp_unslash((string)$_POST['ua'])) : 'OAI-SearchBot';
    $path_raw = isset($_POST['path']) ? \wp_unslash((string)$_POST['path']) : '/'; if(!is_string($path_raw)) $path_raw='/'; if($path_raw==='') $path_raw='/'; if($path_raw[0]!=='/') $path_raw='/'.$path_raw; if(strlen($path_raw)>1024) $path_raw=substr($path_raw,0,1024); $path=sanitize_text_field($path_raw);
    $res=\wp_remote_get(\home_url('/robots.txt'), array('timeout'=>8)); $robots = (!\is_wp_error($res)) ? (string)\wp_remote_retrieve_body($res) : '';
    $allowed=airai_robots_allowed($robots,(string)$ua,$path);
    \wp_send_json_success(array('ua'=>$ua,'path'=>$path,'allowed'=>$allowed));
}
\add_action('wp_ajax_airai_free_get_logs', __NAMESPACE__.'\\airai_free_get_logs');
function airai_free_get_logs(){ \check_ajax_referer('airai_free_ajax'); if(!\current_user_can('manage_options')) \wp_send_json_error(array('message'=>esc_html__('forbidden','ai-readiness-advisor')),403);
    $log=get_option(AIRAI_FREE_LOG_KEY,array()); if(!is_array($log)) $log=array(); \wp_send_json_success(array('log'=>$log)); }
\add_action('wp_ajax_airai_free_clear_logs', __NAMESPACE__.'\\airai_free_clear_logs');
function airai_free_clear_logs(){ \check_ajax_referer('airai_free_ajax'); if(!\current_user_can('manage_options')) \wp_send_json_error(array('message'=>esc_html__('forbidden','ai-readiness-advisor')),403);
    update_option(AIRAI_FREE_LOG_KEY,array(),false); \wp_send_json_success(array('ok'=>true)); }
\add_action('wp_ajax_airai_free_run_quick_test', __NAMESPACE__.'\\airai_free_run_quick_test');
function airai_free_run_quick_test(){ \check_ajax_referer('airai_free_ajax'); if(!\current_user_can('manage_options')) \wp_send_json_error(array('message'=>esc_html__('forbidden','ai-readiness-advisor')),403);
    $ua='ChatGPT-User'; $path='/airai-test'; $home=\home_url('/');
    $hit=\wp_remote_head($home, array('timeout'=>8,'headers'=>array('User-Agent'=>$ua))); $hit_code=\is_wp_error($hit)?0:\wp_remote_retrieve_response_code($hit);
    $ping_url=\add_query_arg(array('path'=>$path), \home_url('/wp-json/airai/v1/ping'));
    $res=\wp_remote_get($ping_url, array('timeout'=>12,'headers'=>array('User-Agent'=>$ua))); $code=\is_wp_error($res)?0:\wp_remote_retrieve_response_code($res);
    $json=array(); if(!\is_wp_error($res)) $json=json_decode(\wp_remote_retrieve_body($res),true);
    \wp_send_json_success(array('http'=>$code,'response'=>$json,'url'=>$ping_url,'ua'=>$ua,'log_hit_status'=>$hit_code));
}
\add_action('wp_ajax_airai_free_run_structured_check', __NAMESPACE__.'\\airai_free_run_structured_check');
function airai_free_run_structured_check(){ \check_ajax_referer('airai_free_ajax'); if(!\current_user_can('manage_options')) \wp_send_json_error(array('message'=>esc_html__('forbidden','ai-readiness-advisor')),403);
    $html_res=\wp_remote_get(\home_url('/'), array('timeout'=>12)); if(\is_wp_error($html_res)) \wp_send_json_error(array('message'=>$html_res->get_error_message()));
    $html=(string)\wp_remote_retrieve_body($html_res); $found=array(); $matches=array();
    if(preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is',$html,$matches)){
        foreach($matches[1] as $blob){ $blob=html_entity_decode($blob, ENT_QUOTES|ENT_HTML5, 'UTF-8'); $j=json_decode(trim($blob), true);
            if(is_array($j)){ $types=array(); $stack=array($j);
                while(!empty($stack)){ $cur=array_pop($stack); if(is_array($cur)){ if(isset($cur['@type'])){ $types[] = is_array($cur['@type']) ? implode(',', $cur['@type']) : $cur['@type']; } foreach($cur as $v){ if(is_array($v)) $stack[]=$v; } } }
                foreach($types as $t){ $t=preg_replace('/\s+/','', (string)$t); if($t!=='') $found[$t]=true; }
        } } }
    $types=array_keys($found); \wp_send_json_success(array('ld_count'=>(isset($matches[0])?count($matches[0]):0),'found'=>$types));
}

/** REST ping */
\add_action('rest_api_init', function(){
    \register_rest_route('airai/v1','/ping', array('methods'=>'GET','callback'=>__NAMESPACE__.'\\airai_rest_ping','permission_callback'=>'__return_true','args'=>array('path'=>array('required'=>false),'ua'=>array('required'=>false))));
});
function airai_rest_ping($request){
    $req_path=$request->get_param('path'); $req_ua=$request->get_param('ua');
    $ua_seen_raw=isset($_SERVER['HTTP_USER_AGENT']) ? \wp_unslash((string)$_SERVER['HTTP_USER_AGENT']) : ''; $ua_seen=sanitize_text_field($ua_seen_raw);
    $ua_eval=(is_string($req_ua)&&$req_ua!=='') ? sanitize_text_field(\wp_unslash((string)$req_ua)) : $ua_seen;
    $path_raw=is_string($req_path)?\wp_unslash((string)$req_path):'/'; if($path_raw==='') $path_raw='/'; if($path_raw[0]!=='/') $path_raw='/'.$path_raw; if(strlen($path_raw)>1024) $path_raw=substr($path_raw,0,1024); $path=sanitize_text_field($path_raw);
    $robots=''; $res=\wp_remote_get(\home_url('/robots.txt'), array('timeout'=>8)); if(!\is_wp_error($res)) $robots=(string)\wp_remote_retrieve_body($res);
    $allowed=airai_robots_allowed($robots,(string)$ua_eval,$path);
    return \rest_ensure_response(array('time'=>current_time('mysql'),'ip'=>sanitize_text_field(isset($_SERVER['REMOTE_ADDR']) ? \wp_unslash((string)$_SERVER['REMOTE_ADDR']) : ''),'host'=>sanitize_text_field(isset($_SERVER['HTTP_HOST']) ? \wp_unslash((string)$_SERVER['HTTP_HOST']) : ''),'ua_seen'=>$ua_seen,'ua_param'=>(is_string($req_ua)?sanitize_text_field(\wp_unslash((string)$req_ua)):null),'ua_effective'=>(string)$ua_eval,'path'=>$path,'allowed'=>$allowed,'robots_head'=>substr((string)$robots,0,2000)));
}
