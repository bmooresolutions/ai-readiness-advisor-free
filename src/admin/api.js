/**
// Ensure no trailing slash duplicates
if (cfg.apiRoot) cfg.apiRoot = cfg.apiRoot.replace(/\/$/, '');
if (cfg.siteRoot) cfg.siteRoot = cfg.siteRoot.replace(/\/$/, '');
return cfg;
}


export async function restGet(cfg, path, params = {}) {
const url = new URL(cfg.apiRoot + path, window.location.origin);
Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
const res = await fetch(url.toString(), {
headers: cfg.restNonce ? { 'X-WP-Nonce': cfg.restNonce } : {}
});
if (!res.ok) throw new Error(`HTTP ${res.status}`);
return res.json();
}


export async function restPost(cfg, path, body = {}) {
const url = cfg.apiRoot + path;
const res = await fetch(url, {
method: 'POST',
headers: {
'Content-Type': 'application/json',
...(cfg.restNonce ? { 'X-WP-Nonce': cfg.restNonce } : {})
},
body: JSON.stringify(body)
});
if (!res.ok) throw new Error(`HTTP ${res.status}`);
return res.json();
}


export async function fetchRobots(cfg) {
// Use REST if your plugin exposes it; otherwise fetch /robots.txt directly
try {
return await restGet(cfg, '/robots');
} catch (e) {
const res = await fetch(`${cfg.siteRoot}/robots.txt`, { credentials: 'include' });
if (!res.ok) throw new Error('robots.txt not found');
const text = await res.text();
return { content: text };
}
}
