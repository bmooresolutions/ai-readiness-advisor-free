import { restPost } from './api.js';


export function initQuickTestView(root, cfg) {
root.innerHTML = '';
const card = document.createElement('div');
card.className = 'aira-card';
card.innerHTML = `
<p>Run a quick write test to confirm the logging pipeline is working.</p>
<button class="button button-primary" data-action="run">Run Quick Test</button>
<span class="aira-status" aria-live="polite"></span>
`;
root.appendChild(card);


const status = card.querySelector('.aira-status');
async function run() {
status.textContent = 'Running…';
try {
const res = await restPost(cfg, '/quick-test', { source: 'admin-ui' });
status.textContent = res && (res.message || 'Logged a test entry.');
} catch (e) {
status.textContent = 'Error: ' + e.message;
}
}
card.querySelector('[data-action="run"]').addEventListener('click', run);
}
