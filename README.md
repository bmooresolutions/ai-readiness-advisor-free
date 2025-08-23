***AI Readiness Advisor (FREE)***

Audit & verify AI-crawler access, preview your robots.txt, generate test hits for logs, visualize AI bot activity, and check JSON-LD (schema.org) — all from your WordPress dashboard.
Version: 1.4.2 · Requires WP 5.4+, PHP 7.2+ · Tested up to 6.8.2

“Get your site AI-ready — without getting lost in the matrix.” 🕶️

✨ ***What it does (FREE)***

* Readiness Dashboard

**Overall Readiness Score with a progress meter.

**AI Bot Activity: top crawlers, last seen, mini meters, and a recent events table.

**Key signals: robots.txt reachable, physical file present, JSON-LD detected, logging status, ping endpoint health.

Verification

Simulate access at / for known user-agents and show Allowed / Blocked / Not specified (based on your live robots.txt).

Custom Path Verification for any path + bot.

Tools

Quick Test (no shell): emulates a bot and writes a log entry instantly (great for shared hosting).

Robots.txt Preview: shows the served file, HTTP status, whether a physical file exists, and a Download starter robots.txt button.

Structured Data Quick Check: detects JSON-LD blocks and lists discovered @types.

Logs

Stores and displays recent AI bot hits (time, bot type, UA, IP, URI).

Refresh and Clear controls.

Help

Copy-paste server snippets for Apache, Nginx, Cloudflare, and WordPress filter examples for dynamic robots.

Bash & PowerShell commands to emulate bots and create log entries.

Starter robots.txt content and DIY instructions.

🔎 Which bots are recognized?

OAI-SearchBot (OpenAI discovery, not training)

ChatGPT-User (link preview fetcher, not training)

PerplexityBot (Perplexity search)

GPTBot (OpenAI training crawler)

Google-Extended (Google AI usage control token)

Applebot-Extended (Apple AI usage control token)

CCBot (Common Crawl)

Reminder: Robots policies are voluntary. Most reputable providers honor them; enforcement at the edge needs server rules or WAF.

🧭 Interpreting results

Allowed — a matching Allow (or no matching Disallow) lets this UA access the path.

Blocked — a matching Disallow (or a more specific disallow rule) denies access.

Not specified — no matching UA group; the decision isn’t defined (the UA falls back to * or defaults).

No physical robots.txt — WordPress may be serving a virtual robots.txt. You’ll see a 200 HTTP status but no file on disk. The plugin shows both the HTTP result and physical presence.

🚀 Getting started

Install & Activate the plugin.

Go to AI Readiness → Tools → Quick Test and click Run to write a log entry.

Check Logs → Refresh to confirm entries.

Visit Dashboard for your Readiness Score and AI Bot Activity.

Open Tools → Robots.txt Preview:

If you don’t have a physical file, click Download starter robots.txt and upload it to your web root.

🧰 Useful snippets (copy/paste)

Apache (block common training bots)

RewriteEngine On
RewriteCond %{HTTP_USER_AGENT} (GPTBot|CCBot|PerplexityBot) [NC]
RewriteRule ^ - [F]


Nginx (same idea)

map $http_user_agent $block_ai {
    default 0;
    ~*(GPTBot|CCBot|PerplexityBot) 1;
}
server {
    if ($block_ai) { return 403; }
}


Cloudflare Firewall expression

(http.user_agent contains "GPTBot" or http.user_agent contains "CCBot" or http.user_agent contains "PerplexityBot")


WordPress dynamic robots filter (example)

add_filter('robots_txt', function($output, $public){
    $custom = "User-agent: GPTBot\nDisallow: /\n";
    return $custom . $output;
}, 10, 2);


Starter robots.txt

Sitemap: https://example.com/sitemap.xml

User-agent: *
Disallow:

User-agent: GPTBot
Disallow: /

User-agent: CCBot
Disallow: /

User-agent: PerplexityBot
Disallow: /

User-agent: ChatGPT-User
Allow: /

User-agent: Google-Extended
Disallow:

User-agent: Applebot-Extended
Disallow:


Create a test log entry (Bash)

curl -A "ChatGPT-User" -sS "https://example.com/wp-json/airai/v1/ping?path=/airai-test"


Create a test log entry (PowerShell)

$Headers = @{ "User-Agent" = "ChatGPT-User" }
Invoke-WebRequest -UseBasicParsing -Uri "https://example.com/wp-json/airai/v1/ping?path=/airai-test" -Headers $Headers | Out-Null

🔐 Security & Privacy

Admin screens require manage_options capability.

Admin-Ajax uses nonces; a guarded fallback endpoint exists for environments where nonces are stripped by caches, still enforcing capability checks.

All superglobals are wp_unslash() + sanitize_text_field() before use.

Logs are stored in the options table under airai_free_bot_log_v1 with a size cap (default 300 entries).

🧩 Troubleshooting

“Failed to load plugin state”:

Ensure admin-ajax.php isn’t blocked by a security plugin or cache layer.

Hard refresh the admin page or clear admin cache.

No log entries after Quick Test:

Check server error logs for firewall/WAF blocks.

Verify REST route: /wp-json/airai/v1/ping.

🆚 Free vs Pro
Feature	FREE	PRO
Readiness score & dashboard activity	✅	✅ (with time filters & export)
Robots.txt preview & starter download	✅	✅ + one-click write to disk (with backup & revert)
Verification (root & custom path)	✅	✅ (batch checks + saved scenarios)
Logs (view, clear)	✅	✅ + CSV export & retention controls
Quick Test (no shell)	✅	✅ (multi-UA test matrix)
Structured Data quick check	✅	✅ (deeper hints + external validators links)
Help: server snippets & commands	✅	✅ (guided wizards + copy buttons)
Policy presets & apply changes	—	✅ (apply to robots.txt dynamically or physical file)
JSON import/export of settings	—	✅
Server snippet generator (Apache/Nginx/Cloudflare)	—	✅ (tailored to site paths)
Scheduler (periodic audit + email)	—	✅
Multisite tools	—	✅
Licensing	—	Lemon Squeezy (single or multi-site)

TL;DR: FREE shows you everything and helps you DIY. PRO performs the changes safely, adds export/automation, and saves you time.

📦 Uninstall

Deactivating leaves data intact. Remove options/logs manually if desired:

Options key: airai_free_options_v1

Logs key: airai_free_bot_log_v1

⚠️ Disclaimer

Compliance with robots.txt depends on the crawler. For stronger enforcement, use server/WAF rules (snippets provided).

🧠 Changelog (highlights)

1.4.2 – Hardened AJAX parsing, nonce fallback (admin-only), stability improvements.

1.4.1 – Sanitized UA early to satisfy plugin checker sniffs.

1.4.0 – AI Bot Activity panel; Robots.txt starter download; Help commands & snippets.
