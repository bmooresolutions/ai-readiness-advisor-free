(function(){
    function qs(s,root){ return (root||document).querySelector(s); }
    function qsa(s,root){ return Array.prototype.slice.call((root||document).querySelectorAll(s)); }
    function ajaxOnce(action, data){
        var f=new FormData(); f.append('action', action); f.append('_ajax_nonce', (window.AIRAI_FREE&&AIRAI_FREE.nonce)||'');
        if (data){ for (var k in data){ if (Object.prototype.hasOwnProperty.call(data,k)) f.append(k, data[k]); } }
        var url=(window.AIRAI_FREE&&AIRAI_FREE.ajaxurl)||'';
        return fetch(url, {method:'POST', credentials:'same-origin', body:f}).then(function(r){
            return r.text().then(function(txt){
                try{ return JSON.parse(txt); }catch(e){ throw new Error('NON_JSON:'+txt.slice(0,200)); }
            });
        });
    }
    function ajax(action, data, cb){
        // Try nonce-protected first; if it fails (nonce expired / NON_JSON '0'), fallback to open admin-only action
        ajaxOnce(action, data).then(function(j){
            if(j && j.success){ cb(null,j); }
            else{
                // fallback
                ajaxOnce(action+'_open', data).then(function(j2){ cb(null,j2); }).catch(function(e2){ cb(e2); });
            }
        }).catch(function(e){
            // if nonce likely failed (code '0' or NON_JSON), try open
            ajaxOnce(action+'_open', data).then(function(j2){ cb(null,j2); }).catch(function(e2){ cb(e2); });
        });
    }
    function pill(kind,text){ var s=document.createElement('span'); s.className='airai-badge '+kind; s.textContent=text; return s; }
    function meter(score){ var wrap=document.createElement('div'); wrap.className='airai-meter'; var span=document.createElement('span'); span.style.width=(score||0)+'%'; wrap.appendChild(span); return wrap; }
    function section(id,title){ var card=document.createElement('div'); card.className='airai-card'; var h=document.createElement('h2'); h.className='airai-title'; h.textContent=title; card.appendChild(h); var box=document.createElement('div'); card.appendChild(box); box.id=id; return card; }
    function setTab(name){
        qsa('.airai-tabs a').forEach(function(a){ a.classList.toggle('active', a.dataset.tab===name); });
        qsa('.airai-tab').forEach(function(div){ div.classList.toggle('airai-hide', div.id!=='tab-'+name); });
        window.sessionStorage.setItem('airai_tab', name);
    }
    function notice(cls, text){ var n=document.createElement('div'); n.className='notice-box '+cls; n.textContent=text; return n; }
    function fmtDate(s){
        if(!s) return '';
        var d=new Date(s.replace(' ', 'T')+'Z');
        if(isNaN(d.getTime())){ d=new Date(s); }
        if(isNaN(d.getTime())) return s;
        return d.toLocaleString();
    }

    function renderDashboard(state, mount){
        var wrap=document.createElement('div'); wrap.className='airai-wrap';
        var row=document.createElement('div'); row.className='airai-row'; wrap.appendChild(row);

        var left=section('dash-left','Readiness Overview'); row.appendChild(left);
        var s=state.readiness && state.readiness.score || 0;
        var k=document.createElement('p'); k.innerHTML='<strong>Readiness: </strong>'+String(s)+'%'; left.lastChild.appendChild(k);
        left.lastChild.appendChild(meter(s));
        left.lastChild.appendChild((function(){
            var ul=document.createElement('ul');
            function add(ok,label,kind){ var li=document.createElement('li'); li.style.margin='4px 0'; li.appendChild(pill(kind, (kind==='ok'?'OK':(kind==='warn'?'WARN':'BLOCK')))); var sp=document.createElement('span'); sp.style.marginLeft='8px'; sp.textContent=' '+label; li.appendChild(sp); ul.appendChild(li); }
            add(state.readiness.breakdown.robots_http_200, 'robots.txt served over HTTP', state.readiness.breakdown.robots_http_200?'ok':'block');
            add(state.readiness.breakdown.physical_robots, 'physical robots.txt in site root', state.readiness.breakdown.physical_robots?'ok':'warn');
            var dyn = (state.servedCode===200 ? (state.robotsPhysical?'warn':'ok') : 'block');
            add(true, 'WordPress dynamic robots.txt (likely when physical missing)', dyn);
            add(state.readiness.breakdown.ua_group_root, 'user-agent rules detectable', state.readiness.breakdown.ua_group_root?'ok':'warn');
            add(state.readiness.breakdown.ping_endpoint, 'REST ping endpoint responded', state.readiness.breakdown.ping_endpoint?'ok':'block');
            add(state.readiness.breakdown.ld_json, 'JSON-LD detected on homepage', state.readiness.breakdown.ld_json?'ok':'warn');
            add(state.readiness.breakdown.logging_enabled, 'logging enabled', state.readiness.breakdown.logging_enabled?'ok':'warn');
            return ul;
        })());

        var right=section('dash-right','AI Bot Activity'); row.appendChild(right);
        var box=right.lastChild;
        box.appendChild(notice('', 'Use Tools -> Quick Test, then open Logs and click Refresh to see entries.'));

        function renderActivity(rows){
            box.innerHTML='';
            if(!rows || !rows.length){
                box.appendChild(notice('', 'No activity yet. Use Quick Test to generate one.'));
                return;
            }
            var byBot = {}; var total = rows.length;
            rows.forEach(function(L){
                var b=L.bot||'Unknown';
                if(!byBot[b]) byBot[b]={count:0,last:null};
                byBot[b].count++;
                if(!byBot[b].last || (L.t && L.t>byBot[b].last)) byBot[b].last=L.t||byBot[b].last;
            });
            var bots = Object.keys(byBot).sort(function(a,b){ return byBot[b].count - byBot[a].count; });
            var top = bots.slice(0,6);

            var grid=document.createElement('div'); grid.className='stat-grid';
            top.forEach(function(b){
                var s=document.createElement('div'); s.className='stat';
                var lab=document.createElement('div'); lab.className='label'; lab.textContent=b;
                var val=document.createElement('div'); val.className='value'; val.textContent=String(byBot[b].count)+' hit(s)';
                var last=document.createElement('div'); last.className='label'; last.textContent='Last: '+fmtDate(byBot[b].last);
                var meterWrap=document.createElement('div'); meterWrap.className='airai-meter'; var span=document.createElement('span');
                var pct = Math.min(100, Math.round(100*byBot[b].count/total)); span.style.width=pct+'%'; meterWrap.appendChild(span);
                s.appendChild(lab); s.appendChild(val); s.appendChild(meterWrap); s.appendChild(last);
                grid.appendChild(s);
            });
            box.appendChild(grid);

            var table=document.createElement('table'); table.className='widefat striped table-clip'; var thead=document.createElement('thead'); thead.innerHTML='<tr><th>Time</th><th>Bot</th><th>UA</th><th>IP</th><th>URI</th></tr>'; table.appendChild(thead);
            var tbody=document.createElement('tbody');
            rows.slice(-10).reverse().forEach(function(L){
                var tr=document.createElement('tr');
                function td(v){ var t=document.createElement('td'); t.textContent=v||''; return t; }
                tr.appendChild(td(fmtDate(L.t)));
                tr.appendChild(td(L.bot||''));
                var tdUa=td(''); var code=document.createElement('code'); code.textContent=L.ua||''; tdUa.appendChild(code); tr.appendChild(tdUa);
                tr.appendChild(td(L.ip||''));
                tr.appendChild(td(L.uri||''));
                tbody.appendChild(tr);
            });
            table.appendChild(tbody);
            box.appendChild(table);
        }

        (function loadActivity(){
            // Use protected endpoint; fallback to open
            ajax('airai_free_get_state', {}, function(err,j){
                if(err || !j || !j.success){
                    box.appendChild(notice('notice-error','Activity load failed.'));
                    return;
                }
                // Fetch logs for live panel
                ajax('airai_free_get_logs', {}, function(err2, j2){
                    if(err2 || !j2 || !j2.success){
                        box.appendChild(notice('notice-error','Failed to load logs.'));
                        return;
                    }
                    renderActivity(j2.data.log||[]);
                });
            });
        })();

        mount.appendChild(wrap);
    }

    function renderVerify(state, mount){
        var wrap=document.createElement('div'); wrap.className='airai-wrap';
        if (!state.robotsPhysical){
            wrap.appendChild(notice('', 'No physical robots.txt detected. WordPress may be serving a dynamic robots.txt; results can be "Not specified". See Help tab to create robots.txt.'));
        }
        var card=document.createElement('div'); card.className='airai-card'; wrap.appendChild(card);
        var h=document.createElement('h3'); h.textContent='Root Access Simulation'; card.appendChild(h);
        var table=document.createElement('table'); table.className='widefat striped table-clip'; card.appendChild(table);
        var thead=document.createElement('thead'); thead.innerHTML='<tr><th>User-Agent</th><th>Access</th><th>About</th></tr>'; table.appendChild(thead);
        var tbody=document.createElement('tbody'); table.appendChild(tbody);
        var explain={
            'OAI-SearchBot':'OpenAI discovery crawler (AI search).',
            'ChatGPT-User':'Fetcher for ChatGPT link previews (not training).',
            'PerplexityBot':'Perplexity search crawler.',
            'GPTBot':'OpenAI training crawler.',
            'Google-Extended':'Google token controlling certain AI data usage.',
            'Applebot-Extended':'Apple extended AI usage control.',
            'CCBot':'Common Crawl bot.'
        };
        (state.verification||[]).forEach(function(r){
            var tr=document.createElement('tr');
            var td1=document.createElement('td'); td1.innerHTML='<code>'+r.ua+'</code>'; tr.appendChild(td1);
            var td2=document.createElement('td'); var v=r.allowed; var kind=(v===null?'warn':(v?'ok':'block')); var label=(v===null?'Not specified':(v?'Allowed':'Blocked')); td2.appendChild(pill(kind,label)); tr.appendChild(td2);
            var td3=document.createElement('td'); td3.textContent=explain[r.ua]||''; tr.appendChild(td3);
            tbody.appendChild(tr);
        });
        var h3=document.createElement('h3'); h3.textContent='Custom Path Verification'; card.appendChild(h3);
        var path=document.createElement('input'); path.type='text'; path.value='/'; path.style.marginRight='8px'; path.placeholder='/path';
        var ua=document.createElement('select'); ['OAI-SearchBot','ChatGPT-User','PerplexityBot','GPTBot','Google-Extended','Applebot-Extended','CCBot'].forEach(function(u){ var o=document.createElement('option'); o.value=u; o.textContent=u; ua.appendChild(o); });
        var btn=document.createElement('button'); btn.className='button'; btn.textContent='Verify';
        var res=document.createElement('div'); res.style.marginTop='8px';
        btn.addEventListener('click', function(){
            ajax('airai_free_verify_custom', {path:path.value, ua:ua.value}, function(err, j){
                res.innerHTML='';
                if (err || !j || !j.success){ res.appendChild(notice('notice-error','Verification failed.')); return; }
                var v=j.data.allowed; var label=(v===null?'Not specified (no matching rule)':(v?'Allowed':'Blocked'));
                var cls=(v===null?'':(v?'notice-success':'notice-error'));
                res.appendChild(notice(cls, 'UA '+j.data.ua+' to '+j.data.path+': '+label));
            });
        });
        var row=document.createElement('div'); row.appendChild(path); row.appendChild(ua); row.appendChild(btn); card.appendChild(row); card.appendChild(res);
        mount.appendChild(wrap);
    }

    function renderLogs(mount){
        var wrap=document.createElement('div'); wrap.className='airai-wrap';
        var card=section('logs','Logs'); wrap.appendChild(card);
        var tools=document.createElement('div'); tools.style.margin='6px 0'; var refresh=document.createElement('a'); refresh.className='button'; refresh.textContent='Refresh'; var clear=document.createElement('a'); clear.className='button button-secondary'; clear.style.marginLeft='8px'; clear.textContent='Clear';
        tools.appendChild(refresh); tools.appendChild(clear); card.lastChild.appendChild(tools);
        var box=document.createElement('div'); card.lastChild.appendChild(box);
        function load(){
            box.innerHTML='Loading...';
            ajax('airai_free_get_logs', {}, function(err,j){
                if(err || !j || !j.success){ box.innerHTML='Failed to load logs.'; return; }
                var rows=j.data.log||[]; if(rows.length===0){ box.innerHTML='No entries yet. Use Tools -> Quick Test to generate one.'; return; }
                var table=document.createElement('table'); table.className='widefat striped table-clip'; var thead=document.createElement('thead'); thead.innerHTML='<tr><th>Time</th><th>Bot</th><th>UA</th><th>IP</th><th>URI</th><th>Host</th></tr>'; table.appendChild(thead);
                var tbody=document.createElement('tbody'); rows.slice().reverse().forEach(function(L){ var tr=document.createElement('tr'); function td(v){ var t=document.createElement('td'); t.textContent=v||''; return t; }
                    tr.appendChild(td(L.t)); tr.appendChild(td(L.bot)); var tdUa=td(''); var code=document.createElement('code'); code.textContent=L.ua||''; tdUa.appendChild(code); tr.appendChild(tdUa);
                    tr.appendChild(td(L.ip)); tr.appendChild(td(L.uri)); tr.appendChild(td(L.host)); tbody.appendChild(tr); });
                table.appendChild(tbody); box.innerHTML=''; box.appendChild(table);
            });
        }
        refresh.addEventListener('click', function(e){ e.preventDefault(); load(); });
        clear.addEventListener('click', function(e){ e.preventDefault(); ajax('airai_free_clear_logs', {}, function(){ load(); }); });
        load();
        mount.appendChild(wrap);
    }

    function renderTools(mount, state){
        var wrap=document.createElement('div'); wrap.className='airai-wrap';

        var card=section('qt','Quick Test (no shell needed)'); wrap.appendChild(card);
        var p=document.createElement('p'); p.textContent='Emulates a bot and writes a log entry; then open the Logs tab and click Refresh.'; card.lastChild.appendChild(p);
        var btn=document.createElement('a'); btn.className='button button-secondary'; btn.textContent='Run'; card.lastChild.appendChild(btn);
        var out=document.createElement('div'); out.style.marginTop='8px'; card.lastChild.appendChild(out);
        btn.addEventListener('click', function(e){
            e.preventDefault(); out.innerHTML='Running...';
            ajax('airai_free_run_quick_test', {}, function(err,j){
                if(err || !j || !j.success){ out.innerHTML='Failed.'; return; }
                var d=j.data||{}; out.innerHTML='Done. Open Logs -> Refresh to view the new entry.'+
                    '<p><strong>Ping URL:</strong> <code>'+ (d.url||'') +'</code></p>'+
                    '<p><strong>HTTP (ping):</strong> <code>'+ String(d.http||0) +'</code></p>'+
                    '<p><strong>Homepage HEAD:</strong> <code>'+ String(d.log_hit_status||0) +'</code></p>';
            });
        });

        var cardR=section('robots','Robots.txt Preview'); wrap.appendChild(cardR);
        var meta=document.createElement('div'); meta.className='small-muted';
        meta.textContent='HTTP status: '+String(state.servedCode||0)+', physical file: '+(state.robotsPhysical?'yes':'no')+'.';
        cardR.lastChild.appendChild(meta);
        var ta=document.createElement('textarea'); ta.className='airai-text'; ta.readOnly=true; ta.value=(state.servedRobots||''); cardR.lastChild.appendChild(ta);
        var rbar=document.createElement('div'); rbar.style.marginTop='8px';
        var refresh=document.createElement('a'); refresh.className='button'; refresh.textContent='Refresh preview';
        rbar.appendChild(refresh);
        var dl=document.createElement('a'); dl.className='button button-secondary'; dl.style.marginLeft='8px'; dl.textContent='Download starter robots.txt';
        rbar.appendChild(dl);
        var hint=document.createElement('div'); hint.className='small-muted'; hint.textContent='Free version is view-only. Copy/paste into a robots.txt in your site root to make changes.';
        cardR.lastChild.appendChild(rbar);
        cardR.lastChild.appendChild(hint);

        function starterText(){
            var home=(window.AIRAI_FREE&&AIRAI_FREE.home)||'/';
            var lines=[];
            lines.push('# Starter robots.txt generated by AI Readiness Advisor');
            lines.push('# Adjust to your needs. Place this file in your web root (document root).');
            lines.push('Sitemap: '+home+'sitemap.xml');
            lines.push('');
            lines.push('User-agent: *');
            lines.push('Disallow:');
            lines.push('');
            lines.push('# Block common model-training crawlers');
            lines.push('User-agent: GPTBot');
            lines.push('Disallow: /');
            lines.push('');
            lines.push('User-agent: CCBot');
            lines.push('Disallow: /');
            lines.push('');
            lines.push('User-agent: PerplexityBot');
            lines.push('Disallow: /');
            lines.push('');
            lines.push('# Allow link previews from ChatGPT');
            lines.push('User-agent: ChatGPT-User');
            lines.push('Allow: /');
            lines.push('');
            lines.push('# Optional tokens (honored by some providers)');
            lines.push('User-agent: Google-Extended');
            lines.push('Disallow:');
            lines.push('');
            lines.push('User-agent: Applebot-Extended');
            lines.push('Disallow:');
            return lines.join('\n');
        }
        dl.addEventListener('click', function(e){
            e.preventDefault();
            var blob=new Blob([starterText()], {type:'text/plain'});
            var url=URL.createObjectURL(blob);
            var a=document.createElement('a'); a.href=url; a.download='robots.txt'; document.body.appendChild(a); a.click(); setTimeout(function(){ URL.revokeObjectURL(url); a.remove(); }, 500);
        });
        refresh.addEventListener('click', function(e){
            e.preventDefault();
            ajax('airai_free_get_state', {}, function(err,j){
                if(err || !j || !j.success){ alert('Failed to refresh.'); return; }
                var d=j.data||{};
                meta.textContent='HTTP status: '+String(d.servedCode||0)+', physical file: '+(d.robotsPhysical?'yes':'no')+'.';
                ta.value=(d.servedRobots||'');
            });
        });

        var card2=section('sd','Structured Data Quick Check'); wrap.appendChild(card2);
        var p2=document.createElement('p'); p2.textContent='Looks for JSON-LD on your homepage and lists detected schema.org @types.'; card2.lastChild.appendChild(p2);
        var btn2=document.createElement('a'); btn2.className='button button-secondary'; btn2.textContent='Run Check'; card2.lastChild.appendChild(btn2);
        var out2=document.createElement('div'); out2.style.marginTop='8px'; card2.lastChild.appendChild(out2);
        btn2.addEventListener('click', function(e){
            e.preventDefault(); out2.innerHTML='Running...';
            ajax('airai_free_run_structured_check', {}, function(err,j){
                if(err || !j || !j.success){ out2.innerHTML='Failed.'; return; }
                var d=j.data||{}; out2.innerHTML='Found '+d.ld_count+' JSON-LD block(s). '+ (d.found && d.found.length? ('Types: '+d.found.join(', ')) : 'No common types detected.');
            });
        });

        mount.appendChild(wrap);
    }

    function renderHelp(mount){
        var wrap=document.createElement('div'); wrap.className='airai-wrap';
        var home=(window.AIRAI_FREE&&AIRAI_FREE.home)||'/'; var ping=home+'wp-json/airai/v1/ping?path=/airai-test';
        function code(label, text){ var card=document.createElement('div'); card.className='airai-card'; var h=document.createElement('h3'); h.textContent=label; card.appendChild(h); var pre=document.createElement('pre'); pre.className='airai-code'; var c=document.createElement('code'); c.textContent=text; pre.appendChild(c); card.appendChild(pre); wrap.appendChild(card); }
        var intro=document.createElement('div'); intro.className='airai-card'; intro.innerHTML='<h2 class="airai-title">Help & Server Snippets</h2><p>Use these to block/allow at the edge (Apache/Nginx/Cloudflare). Free version provides snippets; the Pro version can apply them automatically.</p><p>Tip: If robots.txt returns 200 and there is no physical file, WordPress is likely serving the virtual robots.txt. See status on the Dashboard.</p>'; wrap.appendChild(intro);

        var apache = 'RewriteEngine On\nRewriteCond %{HTTP_USER_AGENT} (GPTBot|CCBot|PerplexityBot) [NC]\nRewriteRule ^ - [F]\n\nRewriteCond %{REQUEST_URI} ^/private [NC]\nRewriteCond %{HTTP_USER_AGENT} !(ChatGPT-User) [NC]\nRewriteRule ^ - [F]\n';
        var nginx = 'map $http_user_agent $block_ai {\n    default 0;\n    ~*(GPTBot|CCBot|PerplexityBot) 1;\n}\nserver {\n    if ($block_ai) { return 403; }\n    location /private { if ($http_user_agent !~* ChatGPT-User) { return 403; } }\n}\n';
        var cf = '(http.user_agent contains "GPTBot" or http.user_agent contains "CCBot" or http.user_agent contains "PerplexityBot")';
        var wpDyn = "add_filter('robots_txt', function($output,$public){ $custom=\"User-agent: GPTBot\\nDisallow: /\\n\"; return $custom.$output; },10,2);";
        code('Apache', apache); code('Nginx', nginx); code('Cloudflare Firewall expression', cf); code('WordPress dynamic robots.txt (PHP)', wpDyn);

        var bash = [
            '# Bash: emulate bots to create a log entry',
            'curl -A "ChatGPT-User" -sS "'+ping+'"',
            'curl -A "OAI-SearchBot" -sS "'+ping+'"',
            'curl -A "PerplexityBot" -sS "'+ping+'"',
            'curl -A "GPTBot" -I "'+home+'"',
            '',
            '# Optional: inspect robots.txt',
            'curl -A "GPTBot" -I "'+home+'robots.txt"'
        ].join('\n');
        code('Bash (Linux/macOS)', bash);

        var ps = [
            '# PowerShell: emulate bots to create a log entry',
            '$Headers = @{}; $Headers["User-Agent"] = "ChatGPT-User";',
            'Invoke-WebRequest -UseBasicParsing -Uri "'+ping+'" -Headers $Headers | Out-Null',
            '$Headers["User-Agent"] = "OAI-SearchBot";',
            'Invoke-WebRequest -UseBasicParsing -Uri "'+ping+'" -Headers $Headers | Out-Null',
            '$Headers["User-Agent"] = "PerplexityBot";',
            'Invoke-WebRequest -UseBasicParsing -Uri "'+ping+'" -Headers $Headers | Out-Null',
            '$Headers["User-Agent"] = "GPTBot";',
            'Invoke-WebRequest -UseBasicParsing -Method Head -Uri "'+home+'" -Headers $Headers | Out-Null',
            '',
            '# Optional: inspect robots.txt',
            'Invoke-WebRequest -UseBasicParsing -Method Head -Uri "'+home+'robots.txt" -Headers $Headers | Format-List *'
        ].join('\n');
        code('PowerShell (Windows)', ps);

        var bashRobots = [
            '# Create a starter robots.txt in current directory',
            'cat > robots.txt << "EOF"',
            'Sitemap: '+home+'sitemap.xml',
            '',
            'User-agent: *',
            'Disallow:',
            '',
            'User-agent: GPTBot',
            'Disallow: /',
            '',
            'User-agent: CCBot',
            'Disallow: /',
            '',
            'User-agent: PerplexityBot',
            'Disallow: /',
            '',
            'User-agent: ChatGPT-User',
            'Allow: /',
            'EOF',
            '',
            'echo "Upload robots.txt to your web root (document root)"'
        ].join('\n');
        code('Bash: create a starter robots.txt', bashRobots);

        mount.appendChild(wrap);
    }

    function buildUI(state){
        var app = qs('#airai-free-app'); if(!app){ return; }
        var tabs = document.createElement('div'); tabs.className='airai-tabs';
        function addTab(name,title){ var a=document.createElement('a'); a.href='#'; a.dataset.tab=name; a.textContent=title; a.addEventListener('click', function(ev){ ev.preventDefault(); setTab(name); }); tabs.appendChild(a); }
        addTab('dashboard','Dashboard'); addTab('verify','Verification'); addTab('logs','Logs'); addTab('tools','Tools'); addTab('help','Help');
        app.appendChild(tabs);

        function pane(name){ var d=document.createElement('div'); d.className='airai-tab'; d.id='tab-'+name; app.appendChild(d); return d; }
        var pDash=pane('dashboard'), pVer=pane('verify'), pLogs=pane('logs'), pTools=pane('tools'), pHelp=pane('help');

        try{ renderDashboard(state, pDash); } catch(e){ pDash.appendChild(notice('notice-error', 'Dashboard render error: '+e.message)); }
        try{ renderVerify(state, pVer); } catch(e){ pVer.appendChild(notice('notice-error', 'Verification render error: '+e.message)); }
        try{ renderLogs(pLogs); } catch(e){ pLogs.appendChild(notice('notice-error', 'Logs render error: '+e.message)); }
        try{ renderTools(pTools, state); } catch(e){ pTools.appendChild(notice('notice-error', 'Tools render error: '+e.message)); }
        try{ renderHelp(pHelp); } catch(e){ pHelp.appendChild(notice('notice-error', 'Help render error: '+e.message)); }

        var current = (window.AIRAI_FREE&&AIRAI_FREE.currentPage?AIRAI_FREE.currentPage:'airai-free-dashboard').replace('airai-free-','');
        var saved = window.sessionStorage.getItem('airai_tab');
        setTab(saved || current || 'dashboard');
    }

    document.addEventListener('DOMContentLoaded', function(){
        var app = qs('#airai-free-app'); if(!app){ return; }
        app.innerHTML = '<div class="airai-wrap"><div class="airai-card">Loading...</div></div>';
        ajax('airai_free_get_state', {}, function(err,j){
            if (err || !j || !j.success){
                app.innerHTML=''; var w=document.createElement('div'); w.className='airai-wrap'; var c=document.createElement('div'); c.className='airai-card'; c.appendChild(notice('notice-error','Failed to load plugin state.')); w.appendChild(c); app.appendChild(w); return;
            }
            app.innerHTML=''; buildUI(j.data);
        });
    });
})();