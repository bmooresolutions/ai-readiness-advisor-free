import { fetchRobots } from './api.js';


export function initRobotsView(root, cfg) {
root.innerHTML = '';
const box = document.createElement('div');
box.className = 'aira-robots';
box.innerHTML = `
<div class="aira-toolbar">
<button class="button" data-action="refresh">Refresh</button>
<span class="aira-note">Shows the current robots.txt used by crawlers.</span>
</div>
<pre class="aira-pre">Loading…</pre>
`;
root.appendChild(box);


const pre = box.querySelector('.aira-pre');
async function load() {
pre.textContent = 'Loading…';
try {
const res = await fetchRobots(cfg);
pre.textContent = (res && res.content) ? String(res.content) : 'robots.txt is empty or not found.';
} catch (e) {
pre.textContent = `Error: ${e.message}`;
}
}


box.querySelector('[data-action="refresh"]').addEventListener('click', load);
load();
}
