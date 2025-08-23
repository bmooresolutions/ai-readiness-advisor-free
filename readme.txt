=== AI Readiness Advisor ===
Stable tag: 1.4.2
== Description ==
Audit and verify AI-crawler access, log known AI user-agents, Quick Test tool (no shell), robots.txt status, readiness score, Help with server snippets/commands, Robots.txt preview + starter download, and live Dashboard activity panel.

== 1.4.2 ==
* Fix: Some installs showed "Failed to load plugin state" due to nonce/JSON issues. Added robust AJAX parser + fallback nonce-less admin-only endpoint.
* Security: Continued use of wp_unslash() + sanitize_text_field() before superglobal access; admin actions restricted to manage_options.
