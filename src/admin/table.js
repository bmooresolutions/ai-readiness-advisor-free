import { restGet } from './api.js';


function renderRows(items) {
if (!items || !items.length) {
tbody.innerHTML = `<tr><td colspan="6">No entries.</td></tr>`;
return;
}
const frag = document.createDocumentFragment();
items.forEach(it => {
const tr = document.createElement('tr');
const ua = safeText(it.user_agent || it.ua || '');
const allowed = it.allowed === true || it.allowed === '1' || it.access === 'allow';
const path = safeText(it.path || it.request_path || '');
const rule = safeText(it.rule || it.robots_rule || '');
const ip = safeText(it.ip || it.remote_addr || '');
const when = fmtWhen(it.when || it.ts || it.time || '');


tr.innerHTML = `
<td class="ua">${ua}</td>
<td class="access ${allowed ? 'allow' : 'deny'}">${allowed ? 'Allowed' : 'Blocked'}</td>
<td class="path">${path}</td>
<td class="rule">${rule}</td>
<td class="ip">${ip}</td>
<td class="when">${when}</td>
`;
frag.appendChild(tr);
});
tbody.innerHTML = '';
tbody.appendChild(frag);
}


function safeText(v) {
const span = document.createElement('span');
span.textContent = String(v ?? '');
return span.innerHTML;
}
function fmtWhen(v) {
try {
const d = new Date(v);
if (!isNaN(d.getTime())) return d.toLocaleString();
} catch {}
return safeText(v);
}


// Events
toolbar.querySelector('[data-action="refresh"]').addEventListener('click', () => load());
filterInput.addEventListener('input', () => {
uaFilter = filterInput.value.trim();
page = 1; pageInfo.textContent = String(page);
load();
});
pager.addEventListener('click', (e) => {
const btn = e.target.closest('button[data-page]');
if (!btn) return;
const dir = btn.dataset.page;
page = Math.max(1, dir === 'next' ? page + 1 : page - 1);
pageInfo.textContent = String(page);
load();
});


load();
}
