=== AI Readiness Advisor ===
Contributors: bmooresolutions
Tags: ai, bots, robots, logs, seo
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.5.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Audit AI bot access: logs, verification table, robots.txt viewer, quick test, server snippets, and a public REST ping endpoint.

== Description ==

AI Readiness Advisor helps you verify and audit access from AI/LLM bots.

**Features**
- Request logs with user-agent details and allow/deny context
- Verification table to check known AI bot access
- robots.txt viewer
- Quick Test (writes a test log entry)
- Public REST ping endpoint for automated checks
- Handy server snippets

**Privacy**
No data is sent to third parties.

== Source Code / Development ==

To comply with WordPress.org’s human-readable code guideline, this plugin ships readable sources and documents build steps.

- Readable sources are included under **/src/**
- Built/minified files are under **/assets/** (e.g., `assets/admin.js`)
- Example mapping:
  - `assets/admin.js` ⟶ built from `src/admin/index.js`

**Build locally**
1. `npm install`
2. `npm run build`

Optionally, a public mirror of these sources is available at:
- <https://github.com/bmooresolutions/ai-readiness-advisor-free>

== Installation ==

1. Upload the plugin and activate.
2. (Optional) Run **Quick Test** to generate a sample log entry.
3. Use the **robots.txt viewer** and **verification** tools to validate access.

== Changelog ==

= 1.5.6 =
* Added www to the plugin URL
