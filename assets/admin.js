/*! AI Readiness Advisor Admin v2.2 */
(function () {
    'use strict';

    function getEnv() {
        return window.AIRAI || {
            ajaxurl: '',
            nonce: '',
            currentPage: 'airai-dashboard',
            home: '/',
            site: '/',
            pluginVersion: '',
            theme: 'default',
            playground: false
        };
    }

    function mountRoot() {
        return document.querySelector('#airai-app');
    }

    function qsa(selector, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector));
    }

    function clearNode(node) {
        while (node.firstChild) {
            node.removeChild(node.firstChild);
        }
    }

    function el(tag, props, children) {
        var node = document.createElement(tag);
        props = props || {};
        children = children || [];

        Object.keys(props).forEach(function (key) {
            var value = props[key];

            if (key === 'className') {
                node.className = value;
            } else if (key === 'text') {
                node.textContent = value;
            } else if (key === 'style' && value && typeof value === 'object') {
                Object.keys(value).forEach(function (styleKey) {
                    node.style[styleKey] = value[styleKey];
                });
            } else if (key === 'attrs' && value && typeof value === 'object') {
                Object.keys(value).forEach(function (attr) {
                    node.setAttribute(attr, value[attr]);
                });
            } else if (key.indexOf('on') === 0 && typeof value === 'function') {
                node.addEventListener(key.substring(2).toLowerCase(), value);
            } else {
                node[key] = value;
            }
        });

        children.forEach(function (child) {
            if (child === null || typeof child === 'undefined') {
                return;
            }
            if (typeof child === 'string') {
                node.appendChild(document.createTextNode(child));
            } else {
                node.appendChild(child);
            }
        });

        return node;
    }

    function section(title, subtitle) {
        var card = el('div', { className: 'airai-card' });
        card.appendChild(el('h2', { className: 'airai-title', text: title }));
        if (subtitle) {
            card.appendChild(el('p', { className: 'small-muted', text: subtitle }));
        }
        var body = el('div');
        card.appendChild(body);
        return { card: card, body: body };
    }

    function badge(kind, text) {
        return el('span', { className: 'airai-badge ' + kind, text: text });
    }

    function allowedBadge(value) {
        if (value === null || typeof value === 'undefined') {
            return badge('warn', 'Not specified');
        }
        return badge(value ? 'ok' : 'block', value ? 'Allowed' : 'Blocked');
    }

    function boolBadge(value, yesLabel, noLabel) {
        return badge(value ? 'ok' : 'block', value ? (yesLabel || 'Yes') : (noLabel || 'No'));
    }

    function meter(score) {
        var safe = Number(score || 0);
        if (safe < 0) safe = 0;
        if (safe > 100) safe = 100;
        var wrap = el('div', { className: 'airai-meter' });
        wrap.appendChild(el('span', { style: { width: safe + '%' } }));
        return wrap;
    }

    function notice(kind, text) {
        return el('div', { className: 'notice-box notice-' + kind, text: text });
    }

    function table(headers, rows) {
        var tbl = el('table', { className: 'widefat striped table-clip' });
        var thead = el('thead');
        var tbody = el('tbody');
        var hr = el('tr');
        headers.forEach(function (h) {
            hr.appendChild(el('th', { text: h }));
        });
        thead.appendChild(hr);

        rows.forEach(function (row) {
            var tr = el('tr');
            row.forEach(function (cell) {
                var td = el('td');
                if (typeof cell === 'string') {
                    td.textContent = cell;
                } else if (Array.isArray(cell)) {
                    cell.forEach(function (item) {
                        if (typeof item === 'string') {
                            td.appendChild(document.createTextNode(item));
                        } else if (item) {
                            td.appendChild(item);
                        }
                    });
                } else if (cell) {
                    td.appendChild(cell);
                }
                tr.appendChild(td);
            });
            tbody.appendChild(tr);
        });

        tbl.appendChild(thead);
        tbl.appendChild(tbody);
        return tbl;
    }

    function ajax(action, data, callback) {
        var env = getEnv();
        var form = new FormData();
        form.append('action', action);
        form.append('_ajax_nonce', env.nonce || '');

        data = data || {};
        Object.keys(data).forEach(function (key) {
            form.append(key, data[key]);
        });

        fetch(env.ajaxurl, {
            method: 'POST',
            credentials: 'same-origin',
            body: form
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (json) {
                callback(null, json);
            })
            .catch(function (error) {
                callback(error);
            });
    }

    function setTab(name) {
        qsa('.airai-tabs a').forEach(function (link) {
            link.classList.toggle('active', link.getAttribute('data-tab') === name);
        });
        qsa('.airai-tab').forEach(function (panel) {
            panel.classList.toggle('airai-hide', panel.id !== 'tab-' + name);
        });
        try {
            window.sessionStorage.setItem('airai_tab', name);
        } catch (e) {}
    }

    function getSavedTab() {
        try {
            return window.sessionStorage.getItem('airai_tab');
        } catch (e) {
            return null;
        }
    }

    function buildShell(app, state) {
        var env = getEnv();
        var wrap = el('div', { className: 'airai-wrap' });

        if (state.playgroundMode || env.playground) {
            wrap.appendChild(notice('success', 'Playground-safe mode is active. The dashboard uses local WordPress data and avoids slow self-HTTP checks.'));
        }

        var tabs = el('div', { className: 'airai-tabs' });
        var tabNames = [
            ['dashboard', 'Dashboard'],
            ['verification', 'Verification'],
            ['audit', 'Audit'],
            ['policies', 'Policies'],
            ['tools', 'Tools'],
            ['logs', 'Logs'],
            ['help', 'Help']
        ];

        tabNames.forEach(function (item) {
            var link = el('a', {
                href: '#',
                text: item[1],
                attrs: { 'data-tab': item[0] },
                onclick: function (event) {
                    event.preventDefault();
                    setTab(item[0]);
                }
            });
            tabs.appendChild(link);
        });

        wrap.appendChild(tabs);

        var panels = {};
        tabNames.forEach(function (item) {
            panels[item[0]] = el('div', {
                className: 'airai-tab airai-hide',
                attrs: { id: 'tab-' + item[0] }
            });
            wrap.appendChild(panels[item[0]]);
        });

        app.appendChild(wrap);
        return panels;
    }

    function renderDashboard(panel, shellState, dashboardData) {
        clearNode(panel);

        var overview = section('Readiness Overview', 'Fast baseline checks loaded without slow network recursion.');
        var score = (dashboardData.readiness && dashboardData.readiness.score) || 0;
        overview.body.appendChild(el('p', { text: (dashboardData.readiness && dashboardData.readiness.summary) || 'No summary available.' }));
        overview.body.appendChild(el('p', { text: 'Readiness score: ' + score + '%' }));
        overview.body.appendChild(meter(score));

        var checks = (dashboardData.readiness && dashboardData.readiness.checks) || [];
        if (checks.length) {
            var list = el('ul', { style: { paddingLeft: '18px' } });
            checks.forEach(function (check) {
                list.appendChild(el('li', { text: check.label || check.message || '' }));
            });
            overview.body.appendChild(list);
        }

        panel.appendChild(overview.card);

        var robots = section('robots.txt Status');
        robots.body.appendChild(table(
            ['Signal', 'Value'],
            [
                ['HTTP status', String(dashboardData.servedCode || 0)],
                ['Physical file', boolBadge(!!dashboardData.robotsPhysical, 'Yes', 'No')],
                ['Dynamic WordPress output likely', boolBadge(!!dashboardData.robotsDynamicLikely, 'Yes', 'No')]
            ]
        ));
        robots.body.appendChild(el('pre', { className: 'airai-code' }, [
            el('code', { text: dashboardData.robotsHead || dashboardData.servedRobots || 'No robots.txt content available.' })
        ]));
        panel.appendChild(robots.card);

        var sitemap = section('Sitemap Discovery');
        var sitemapRows = [];
        var map = dashboardData.sitemap || {};
        Object.keys(map).forEach(function (key) {
            var item = map[key] || {};
            sitemapRows.push([
                item.label || key,
                item.url || '',
                typeof item.status !== 'undefined' ? String(item.status) : '',
                item.found ? boolBadge(true, 'Found', 'Not found') : boolBadge(false, 'Found', 'Not found')
            ]);
        });
        if (sitemapRows.length) {
            sitemap.body.appendChild(table(['Type', 'URL', 'Status', 'Result'], sitemapRows));
        } else {
            sitemap.body.appendChild(notice('warning', 'No sitemap data available.'));
        }
        panel.appendChild(sitemap.card);

        var homepage = section('Homepage Signals');
        var hp = dashboardData.homepage || {};
        homepage.body.appendChild(table(
            ['Signal', 'Value'],
            [
                ['Homepage URL', hp.url || ''],
                ['HTTP status', String(hp.status || 0)],
                ['Meta robots', hp.metaRobots || ''],
                ['X-Robots-Tag', hp.xRobotsTag || ''],
                ['Canonical', hp.canonical || ''],
                ['JSON-LD detected', boolBadge(!!hp.jsonLd, 'Yes', 'No')]
            ]
        ));
        panel.appendChild(homepage.card);

        var actors = section('Known AI Actors');
        function actorList(title, items) {
            var box = el('div', { className: 'airai-card', style: { marginTop: '12px' } });
            box.appendChild(el('h3', { text: title }));
            var list = el('ul', { style: { paddingLeft: '18px' } });
            (items || []).forEach(function (item) {
                var text = item.label ? (item.label + ' — ' + item.token) : (item.token || '');
                list.appendChild(el('li', { text: text }));
            });
            box.appendChild(list);
            return box;
        }
        actors.body.appendChild(actorList('Crawlers', (shellState.knownActors && shellState.knownActors.crawlers) || []));
        actors.body.appendChild(actorList('Fetchers', (shellState.knownActors && shellState.knownActors.fetchers) || []));
        actors.body.appendChild(actorList('Policy tokens', (shellState.knownActors && shellState.knownActors.policyTokens) || []));
        panel.appendChild(actors.card);
    }

    function renderVerification(panel, dashboardData) {
        clearNode(panel);
        var card = section('Verification');
        var rows = (dashboardData.verification || []).map(function (item) {
            return [
                el('code', { text: item.ua || '' }),
                allowedBadge(item.allowed)
            ];
        });

        if (rows.length) {
            card.body.appendChild(table(['User-Agent', 'Root access'], rows));
        } else {
            card.body.appendChild(notice('warning', 'No verification rows available.'));
        }

        var formWrap = el('div', { style: { marginTop: '12px' } });
        var select = el('select');
        [
            'OAI-SearchBot',
            'ChatGPT-User',
            'GPTBot',
            'CCBot',
            'PerplexityBot',
            'Google-Extended',
            'Applebot-Extended'
        ].forEach(function (token) {
            select.appendChild(el('option', { value: token, text: token }));
        });
        var input = el('input', { type: 'text', value: '/', placeholder: '/path', style: { marginLeft: '8px' } });
        var button = el('button', { type: 'button', className: 'button button-primary', text: 'Verify', style: { marginLeft: '8px' } });
        var output = el('span', { style: { marginLeft: '8px' } });

        button.addEventListener('click', function () {
            output.textContent = 'Checking...';
            ajax('airai_verify_custom', { ua: select.value, path: input.value }, function (err, json) {
                if (err || !json || !json.success || !json.data) {
                    output.textContent = 'Verification failed.';
                    return;
                }
                var allowed = json.data.allowed;
                output.textContent = allowed === null || typeof allowed === 'undefined'
                    ? 'Not specified'
                    : (allowed ? 'Allowed' : 'Blocked');
            });
        });

        formWrap.appendChild(select);
        formWrap.appendChild(input);
        formWrap.appendChild(button);
        formWrap.appendChild(output);
        card.body.appendChild(formWrap);

        panel.appendChild(card.card);
    }

    function renderAudit(panel, auditData) {
        clearNode(panel);

        var card = section('Important URL Audit');
        var matrix = auditData.crawlMatrix || [];

        if (!matrix.length) {
            card.body.appendChild(notice('warning', 'No audit matrix available.'));
            panel.appendChild(card.card);
            return;
        }

        var actors = [];
        matrix.forEach(function (row) {
            Object.keys(row.rules || {}).forEach(function (name) {
                if (actors.indexOf(name) === -1) {
                    actors.push(name);
                }
            });
        });

        var headers = ['URL'].concat(actors);
        var rows = matrix.map(function (row) {
            var out = [el('code', { text: row.url || '' })];
            actors.forEach(function (actor) {
                out.push(allowedBadge((row.rules || {})[actor]));
            });
            return out;
        });

        card.body.appendChild(table(headers, rows));
        panel.appendChild(card.card);
    }

    function renderPolicies(panel, auditData) {
        clearNode(panel);

        var card = section('Policy Builder');
        var templates = auditData.policyTemplates || [];
        if (!templates.length) {
            card.body.appendChild(notice('warning', 'No policy templates available.'));
            panel.appendChild(card.card);
            return;
        }

        var select = el('select');
        var desc = el('p', { className: 'small-muted' });
        var pre = el('pre', { className: 'airai-code' }, [el('code', { text: '' })]);
        var download = el('button', { type: 'button', className: 'button', text: 'Download sample', style: { marginTop: '10px' } });

        templates.forEach(function (tpl, index) {
            select.appendChild(el('option', { value: String(index), text: tpl.label || tpl.slug || ('Template ' + (index + 1)) }));
        });

        function showTemplate(index) {
            var tpl = templates[index] || {};
            desc.textContent = tpl.description || '';
            pre.querySelector('code').textContent = tpl.content || '';
            download.setAttribute('data-template', tpl.slug || 'balanced');
        }

        select.addEventListener('change', function () {
            showTemplate(parseInt(select.value, 10) || 0);
        });

        download.addEventListener('click', function () {
            var env = getEnv();
            var tpl = download.getAttribute('data-template') || 'balanced';
            var url = env.ajaxurl + '?action=airai_download_sample_robots&_ajax_nonce=' +
                encodeURIComponent(env.nonce || '') + '&template=' + encodeURIComponent(tpl);
            var a = document.createElement('a');
            a.href = url;
            document.body.appendChild(a);
            a.click();
            a.remove();
        });

        showTemplate(0);
        card.body.appendChild(select);
        card.body.appendChild(desc);
        card.body.appendChild(pre);
        card.body.appendChild(download);

        panel.appendChild(card.card);
    }

    function renderTools(panel) {
        clearNode(panel);

        var quick = section('Quick Test', 'Adds a sample bot log entry without shell access.');
        var runButton = el('button', { type: 'button', className: 'button button-secondary', text: 'Run Quick Test' });
        var runOutput = el('div', { className: 'small-muted', style: { marginTop: '8px' } });

        runButton.addEventListener('click', function () {
            runOutput.textContent = 'Running...';
            ajax('airai_run_quick_test', {}, function (err, json) {
                if (err || !json || !json.success) {
                    runOutput.textContent = 'Quick test failed.';
                    return;
                }
                runOutput.textContent = 'Quick test complete. Open Logs to review the new entry.';
            });
        });

        quick.body.appendChild(runButton);
        quick.body.appendChild(runOutput);

        var snippets = section('Test Snippets');
        var env = getEnv();
        var home = env.home || '/';
        snippets.body.appendChild(el('pre', { className: 'airai-code' }, [
            el('code', { text: 'curl -A "ChatGPT-User" "' + home + 'wp-json/airai/v1/ping?path=/"' })
        ]));
        snippets.body.appendChild(el('pre', { className: 'airai-code' }, [
            el('code', { text: 'Invoke-WebRequest -UserAgent "ChatGPT-User" "' + home + 'wp-json/airai/v1/ping?path=/" | Select-Object -Expand Content' })
        ]));

        panel.appendChild(quick.card);
        panel.appendChild(snippets.card);
    }

    function renderLogs(panel) {
        clearNode(panel);

        var card = section('Logs');
        var controls = el('div', { style: { marginBottom: '10px' } });
        var refresh = el('button', { type: 'button', className: 'button', text: 'Refresh' });
        var clearBtn = el('button', { type: 'button', className: 'button button-secondary', text: 'Clear', style: { marginLeft: '8px' } });
        var out = el('div');

        function loadLogs() {
            clearNode(out);
            out.appendChild(el('div', { text: 'Loading logs...' }));
            ajax('airai_get_logs', {}, function (err, json) {
                clearNode(out);
                if (err || !json || !json.success || !json.data) {
                    out.appendChild(notice('error', 'Failed to load logs.'));
                    return;
                }

                var rows = json.data.log || [];
                if (!rows.length) {
                    out.appendChild(notice('warning', 'No log entries yet.'));
                    return;
                }

                var mapped = rows.slice().reverse().map(function (entry) {
                    return [
                        entry.t || '',
                        entry.bot || '',
                        el('code', { text: entry.ua || '' }),
                        entry.ip || '',
                        entry.uri || '',
                        entry.host || ''
                    ];
                });

                out.appendChild(table(['Time', 'Bot', 'User-Agent', 'IP', 'URI', 'Host'], mapped));
            });
        }

        refresh.addEventListener('click', function () { loadLogs(); });
        clearBtn.addEventListener('click', function () {
            ajax('airai_clear_logs', {}, function () { loadLogs(); });
        });

        controls.appendChild(refresh);
        controls.appendChild(clearBtn);
        card.body.appendChild(controls);
        card.body.appendChild(out);
        panel.appendChild(card.card);

        loadLogs();
    }

    function renderHelp(panel, shellState) {
        clearNode(panel);

        var card = section('Help', 'The split-load architecture keeps the admin UI responsive by loading dashboard and audit data separately.');
        card.body.appendChild(el('ul', { style: { paddingLeft: '18px' } }, [
            el('li', { text: 'Dashboard and robots status load from local WordPress data.' }),
            el('li', { text: 'Audit data loads separately so one slow section cannot blank the whole page.' }),
            el('li', { text: 'Playground-safe mode avoids recursive self-HTTP requests.' }),
            el('li', { text: 'Version: ' + (shellState.version || '') })
        ]));
        panel.appendChild(card.card);
    }

    function tabForPage(currentPage) {
        var map = {
            'airai-dashboard': 'dashboard',
            'airai-verification': 'verification',
            'airai-audit': 'audit',
            'airai-policies': 'policies',
            'airai-tools': 'tools',
            'airai-logs': 'logs',
            'airai-help': 'help'
        };
        return map[currentPage] || 'dashboard';
    }

    function init() {
        var app = mountRoot();
        if (!app) {
            return;
        }

        clearNode(app);
        app.appendChild(el('div', { className: 'airai-wrap' }, [
            el('div', { className: 'airai-card', text: 'Loading shell...' })
        ]));

        ajax('airai_get_state', {}, function (shellErr, shellJson) {
            if (shellErr || !shellJson || !shellJson.success || !shellJson.data) {
                clearNode(app);
                app.appendChild(notice('error', 'Failed to load plugin shell.'));
                return;
            }

            clearNode(app);
            var shellState = shellJson.data;
            var panels = buildShell(app, shellState);

            panels.dashboard.appendChild(notice('warning', 'Loading dashboard data...'));
            panels.verification.appendChild(notice('warning', 'Loading verification data...'));
            panels.audit.appendChild(notice('warning', 'Loading audit data...'));
            panels.policies.appendChild(notice('warning', 'Loading policy data...'));
            panels.tools.appendChild(notice('warning', 'Loading tools...'));
            panels.logs.appendChild(notice('warning', 'Loading logs...'));
            panels.help.appendChild(notice('warning', 'Loading help...'));

            renderTools(panels.tools);
            renderHelp(panels.help, shellState);
            renderLogs(panels.logs);

            ajax('airai_get_dashboard_data', {}, function (dashErr, dashJson) {
                if (dashErr || !dashJson || !dashJson.success || !dashJson.data) {
                    clearNode(panels.dashboard);
                    panels.dashboard.appendChild(notice('error', 'Failed to load dashboard data.'));
                    clearNode(panels.verification);
                    panels.verification.appendChild(notice('error', 'Failed to load verification data.'));
                    return;
                }

                renderDashboard(panels.dashboard, shellState, dashJson.data);
                renderVerification(panels.verification, dashJson.data);
            });

            ajax('airai_get_audit_data', {}, function (auditErr, auditJson) {
                if (auditErr || !auditJson || !auditJson.success || !auditJson.data) {
                    clearNode(panels.audit);
                    panels.audit.appendChild(notice('error', 'Failed to load audit data.'));
                    clearNode(panels.policies);
                    panels.policies.appendChild(notice('error', 'Failed to load policy data.'));
                    return;
                }

                renderAudit(panels.audit, auditJson.data);
                renderPolicies(panels.policies, auditJson.data);
            });

            var startTab = getSavedTab() || tabForPage(getEnv().currentPage);
            setTab(startTab);
        });
    }

    document.addEventListener('DOMContentLoaded', init);
})();
