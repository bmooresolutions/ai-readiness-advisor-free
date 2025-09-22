/*! AI Readiness Advisor – Admin UI (source)
let mount = document.getElementById('aira-admin');
if (!mount) {
mount = document.createElement('div');
mount.id = 'aira-admin';
wpBody.prepend(mount);
}


mount.classList.add('aira-wrapper');


// Simple tabs: Logs | Robots.txt | Quick Test
mount.innerHTML = `
<div class="aira-header">
<h1 class="aira-title">AI Readiness Advisor</h1>
<p class="aira-subtitle">Audit AI/LLM bot access & verify robots rules.</p>
</div>
<div class="aira-tabs">
<button class="button tab is-active" data-tab="logs">Logs</button>
<button class="button tab" data-tab="robots">Robots.txt</button>
<button class="button tab" data-tab="quick">Quick Test</button>
</div>
<div class="aira-content">
<section class="tab-panel" data-panel="logs"></section>
<section class="tab-panel is-hidden" data-panel="robots"></section>
<section class="tab-panel is-hidden" data-panel="quick"></section>
</div>
`;


const panels = {
logs: mount.querySelector('[data-panel="logs"]'),
robots: mount.querySelector('[data-panel="robots"]'),
quick: mount.querySelector('[data-panel="quick"]')
};


// Initialize default view
initLogsView(panels.logs, cfg);


// Tab switching
mount.querySelectorAll('.aira-tabs .tab').forEach(btn => {
btn.addEventListener('click', () => {
mount.querySelectorAll('.aira-tabs .tab').forEach(b => b.classList.remove('is-active'));
btn.classList.add('is-active');
const tab = btn.dataset.tab;
mount.querySelectorAll('.tab-panel').forEach(p => p.classList.add('is-hidden'));
panels[tab].classList.remove('is-hidden');


// Lazy init each view when first opened
if (tab === 'robots' && !panels.robots.dataset.inited) {
initRobotsView(panels.robots, cfg); panels.robots.dataset.inited = '1';
}
if (tab === 'quick' && !panels.quick.dataset.inited) {
initQuickTestView(panels.quick, cfg); panels.quick.dataset.inited = '1';
}
});
});
})();
