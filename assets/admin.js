(function(){
    function pickEnv(){
        if (window.AIRAI) return window.AIRAI;
        if (window.AIRAI_FREE) return window.AIRAI_FREE;
        return { ajaxurl:'', nonce:'', currentPage:'airai-dashboard', home:'/', site:'/', pluginVersion:'', theme:'default' };
    }
    function mountRoot(){
        return document.querySelector('#airai-free-app') || document.querySelector('#airai-app');
    }
    function qsa(s,root){ return Array.prototype.slice.call((root||document).querySelectorAll(s)); }
    function ajax(action, data, cb){
        var env = pickEnv();
        var f=new FormData();
        f.append('action', action);
        f.append('_ajax_nonce', env.nonce||'');
        if (data){ for (var k in data){ if (Object.prototype.hasOwnProperty.call(data,k)) f.append(k, data[k]); } }
        fetch(env.ajaxurl||'', {method:'POST', credentials:'same-origin', body:f})
            .then(function(r){ return r.json(); })
            .then(function(j){ cb(null,j); })
            .catch(function(e){ cb(e); });
    }
    function pill(kind,text){ var s=document.createElement('span'); s.className='airai-badge '+kind; s.textContent=text; return s; }
    function meter(score){ var wrap=document.createElement('div'); wrap.className='airai-meter'; var span=document.createElement('span'); span.style.width=(score||0)+'%'; wrap.appendChild(span); return wrap; }
    function section(title){ var card=document.createElement('div'); card.className='airai-card'; var h=document.createElement('h2'); h.className='airai-title'; h.textContent=title; card.appendChild(h); var box=document.createElement('div'); card.appendChild(box); return {card:card,box:box}; }
    function setTab(name){ qsa('.airai-tabs a').forEach(function(a){ a.classList.toggle('active', a.dataset.tab===name); }); qsa('.airai-tab').forEach(function(div){ div.classList.toggle('airai-hide', div.id!=='tab-'+name); }); try{ window.sessionStorage.setItem('airai_free_tab', name); }catch(e){} }

    function robotsCard(state){
        var card=section('robots.txt Status');
        var list=document.createElement('ul'); list.style.margin='0'; list.style.paddingLeft='16px';
        function li(t){ var x=document.createElement('li'); x.textContent=t; return x; }
        list.appendChild(li('HTTP status: '+String(state.servedCode||0)));
        list.appendChild(li('Physical file: '+(state.robotsPhysical?'yes':'no')));
        list.appendChild(li('Likely dynamic (WP): '+(state.robotsDynamicLikely?'yes':'no')));
        card.box.appendChild(list);
        var pre=document.createElement('pre'); pre.className='airai-code'; var c=document.createElement('code'); c.textContent=(state.robotsHead||''); pre.appendChild(c); card.box.appendChild(pre);
        var hint=document.createElement('div'); hint.className='small-muted'; hint.textContent='Full content available under Tools → Robots.txt Viewer.'; card.box.appendChild(hint);
        return card.card;
    }

    function renderDashboard(state, mount){
        var wrap=document.createElement('div'); wrap.className='airai-wrap';
        var row=document.createElement('div'); row.className='airai-row'; wrap.appendChild(row);
        var left=section('Readiness Overview'); row.appendChild(left.card);
        var s=(state.readiness&&state.readiness.score)||0;
        var k=document.createElement('p'); k.innerHTML='<strong>Readiness: </strong>'+String(s)+'%'; left.box.appendChild(k);
        left.box.appendChild(meter(s));
        var tips=document.createElement('p'); tips.className='small-muted'; tips.textContent=(state.robotsPhysical?'Physical robots.txt detected.':'No physical robots.txt file found. WordPress may be serving a virtual robots.txt.'); left.box.appendChild(tips);

        row.appendChild(robotsCard(state));

        var right=section('AI Bot Activity'); wrap.appendChild(right.card);
        var info=document.createElement('div'); info.className='small-muted'; info.textContent='Use Tools → Quick Test, then open Logs and click Refresh to see entries.'; right.box.appendChild(info);
        mount.appendChild(wrap);
    }

    function renderVerify(state, mount){
        var wrap=document.createElement('div'); wrap.className='airai-wrap';
        var card=section('Root Access Simulation'); wrap.appendChild(card.card);
        var table=document.createElement('table'); table.className='widefat striped table-clip'; table.innerHTML='<thead><tr><th>User-Agent</th><th title=\"Allowed if robots rules permit root access\">Access</th></tr></thead>'; var tb=document.createElement('tbody'); (state.verification||[]).forEach(function(r){ var tr=document.createElement('tr'); var td1=document.createElement('td'); var code=document.createElement('code'); code.textContent=r.ua; td1.appendChild(code); tr.appendChild(td1); var td2=document.createElement('td'); var v=r.allowed; var kind=(v===null?'warn':(v?'ok':'block')); var label=(v===null?'Not specified':(v?'Allowed':'Blocked')); td2.appendChild(pill(kind,label)); tr.appendChild(td2); tb.appendChild(tr); }); table.appendChild(tb); card.box.appendChild(table);

        var form=document.createElement('div'); form.style.marginTop='10px'; form.innerHTML='<strong>Custom Path Verification</strong>';
        var sel=document.createElement('select'); ['OAI-SearchBot','ChatGPT-User','PerplexityBot','GPTBot','Google-Extended','Applebot-Extended','CCBot'].forEach(function(u){ var o=document.createElement('option'); o.value=u; o.textContent=u; sel.appendChild(o); });
        var input=document.createElement('input'); input.type='text'; input.value='/'; input.placeholder='/path'; input.title='Path to test against robots rules';
        var btn=document.createElement('a'); btn.className='button'; btn.textContent='Verify'; var out=document.createElement('span'); out.style.marginLeft='8px';
        form.appendChild(document.createTextNode(' UA: ')); form.appendChild(sel); form.appendChild(document.createTextNode(' Path: ')); form.appendChild(input); form.appendChild(btn); form.appendChild(out);
        card.box.appendChild(form);
        btn.addEventListener('click', function(e){ e.preventDefault(); out.textContent='…'; ajax('airai_free_verify_custom', {ua:sel.value, path:input.value}, function(err,j){ if(err||!j||!j.success){ out.textContent='Failed.'; return; } var v=j.data.allowed; out.textContent=(v===null?'Not specified':(v?'Allowed':'Blocked')); }); });

        mount.appendChild(wrap);
    }

    function renderTools(state, mount){
        var wrap=document.createElement('div'); wrap.className='airai-wrap';
        var qt=section('Quick Test (no shell needed)'); wrap.appendChild(qt.card);
        var p=document.createElement('p'); p.textContent='Emulates a bot and writes a log entry; then open Logs and click Refresh.'; qt.box.appendChild(p);
        var btn=document.createElement('a'); btn.className='button button-secondary'; btn.textContent='Run'; var out=document.createElement('div'); out.style.marginTop='8px';
        qt.box.appendChild(btn); qt.box.appendChild(out);
        btn.addEventListener('click', function(e){ e.preventDefault(); out.textContent='Running…'; ajax('airai_free_run_quick_test', {}, function(err,j){ out.textContent= (err||!j||!j.success)?'Failed.':'Done. Check Logs.'; }); });

        var rob=section('Robots.txt Viewer'); wrap.appendChild(rob.card);
        var meta=document.createElement('div'); meta.className='small-muted'; meta.textContent='HTTP status: '+String(state.servedCode||0)+', physical: '+(state.robotsPhysical?'yes':'no')+'.'; rob.box.appendChild(meta);
        var pre=document.createElement('pre'); pre.className='airai-code'; var code=document.createElement('code'); code.textContent=(state.servedRobots||''); pre.appendChild(code); rob.box.appendChild(pre);
        var env=pickEnv(); var dl=document.createElement('a'); dl.className='button'; dl.textContent='Download sample robots.txt'; dl.addEventListener('click', function(e){ e.preventDefault(); var a=document.createElement('a'); a.href=(env.ajaxurl+'?action=airai_free_download_sample_robots&_ajax_nonce='+encodeURIComponent(env.nonce)); document.body.appendChild(a); a.click(); a.remove(); }); rob.box.appendChild(dl);
		var p=document.createElement('p'); p.textContent='Copy this robots.txt file to the root of your website directory.'; qt.box.appendChild(p);

        mount.appendChild(wrap);
    }

    function renderLogs(mount){
        var wrap=document.createElement('div'); wrap.className='airai-wrap'; var box=section('Logs'); wrap.appendChild(box.card);
        var tools=document.createElement('div'); tools.style.margin='6px 0'; var refresh=document.createElement('a'); refresh.className='button'; refresh.textContent='Refresh'; var clear=document.createElement('a'); clear.className='button button-secondary'; clear.style.marginLeft='8px'; clear.textContent='Clear'; tools.appendChild(refresh); tools.appendChild(clear); box.box.appendChild(tools);
        var out=document.createElement('div'); box.box.appendChild(out);
        function load(){ out.innerHTML='Loading...'; ajax('airai_free_get_logs', {}, function(err,j){ if(err||!j||!j.success){ out.innerHTML='Failed.'; return;} var rows=j.data.log||[]; if(rows.length===0){ out.innerHTML='No entries yet.'; return;} var table=document.createElement('table'); table.className='widefat striped table-clip'; table.innerHTML='<thead><tr><th>Time</th><th>Bot</th><th>UA</th><th>IP</th><th>URI</th><th>Host</th></tr></thead>'; var tb=document.createElement('tbody'); rows.slice().reverse().forEach(function(L){ var tr=document.createElement('tr'); function td(v){ var t=document.createElement('td'); t.textContent=v||''; return t; } tr.appendChild(td(L.t)); tr.appendChild(td(L.bot)); var tdu=td(''); var c=document.createElement('code'); c.textContent=L.ua||''; tdu.appendChild(c); tr.appendChild(tdu); tr.appendChild(td(L.ip)); tr.appendChild(td(L.uri)); tr.appendChild(td(L.host)); tb.appendChild(tr);}); table.appendChild(tb); out.innerHTML=''; out.appendChild(table); }); }
        refresh.addEventListener('click', function(e){ e.preventDefault(); load(); }); clear.addEventListener('click', function(e){ e.preventDefault(); ajax('airai_free_clear_logs', {}, function(){ load(); }); });
        load(); mount.appendChild(wrap);
    }

    function renderHelp(mount){
        var wrap=document.createElement('div'); wrap.className='airai-wrap';
        var intro=section('Help & Snippets'); wrap.appendChild(intro.card); intro.box.innerHTML='<p>Copy these commands to generate log entries and test your robots rules.</p>';
        function code(label, text){ var card=document.createElement('div'); card.className='airai-card'; var h=document.createElement('h3'); h.textContent=label; card.appendChild(h); var pre=document.createElement('pre'); pre.className='airai-code'; var c=document.createElement('code'); c.textContent=text; pre.appendChild(c); card.appendChild(pre); wrap.appendChild(card); }
        var env=pickEnv(), home=(env.home||'/');
        code('Bash (curl) — emulate ChatGPT-User', 'curl -A \"ChatGPT-User\" \"'+home+'wp-json/airai/v1/ping?path=/\"');
        code('PowerShell', 'Invoke-WebRequest -UserAgent \"ChatGPT-User\" \"'+home+'wp-json/airai/v1/ping?path=/\" | Select-Object -Expand Content');
        code('Apache vhost snippet', '<Location \"/\">\\n  SetEnvIfNoCase User-Agent \"GPTBot|CCBot|PerplexityBot\" ai_block=1\\n  Order allow,deny\\n  Allow from all\\n  Deny from env=ai_block\\n</Location>');
        code('Nginx snippet', 'map $http_user_agent $ai_block {\\n  default 0;\\n  ~*(GPTBot|CCBot|PerplexityBot) 1;\\n}\\nserver {\\n  if ($ai_block) { return 403; }\\n}');
        code('Cloudflare WAF idea', 'Field: http.user_agent — contains any of [GPTBot, CCBot, PerplexityBot] → Block/Challenge');
        mount.appendChild(wrap);
    }

    function buildUI(state){
        var app = mountRoot(); if(!app){ return; }
        var tabs = document.createElement('div'); tabs.className='airai-tabs';
        function addTab(name,title){ var a=document.createElement('a'); a.href='#'; a.dataset.tab=name; a.textContent=title; a.addEventListener('click', function(ev){ ev.preventDefault(); setTab(name); }); tabs.appendChild(a); }
        addTab('dashboard','Dashboard'); addTab('verify','Verification'); addTab('tools','Tools'); addTab('logs','Logs'); addTab('help','Help');
        app.appendChild(tabs);
        function pane(name){ var d=document.createElement('div'); d.className='airai-tab'; d.id='tab-'+name; app.appendChild(d); return d; }
        var pDash=pane('dashboard'), pVer=pane('verify'), pTools=pane('tools'), pLogs=pane('logs'), pHelp=pane('help');
        try{ renderDashboard(state, pDash); }catch(e){}
        try{ renderVerify(state, pVer); }catch(e){}
        try{ renderTools(state, pTools); }catch(e){}
        try{ renderLogs(pLogs); }catch(e){}
        try{ renderHelp(pHelp); }catch(e){}
        var saved = null; try{ saved = window.sessionStorage.getItem('airai_free_tab'); }catch(e){}
        var env = pickEnv(); var current=(env.currentPage||'airai-dashboard').replace('airai-','');
        setTab(saved || current || 'dashboard');
    }

    document.addEventListener('DOMContentLoaded', function(){
        var app = mountRoot(); if(!app){ return; }
        app.innerHTML = '<div class=\"airai-wrap\"><div class=\"airai-card\">Loading...</div></div>';
        ajax('airai_free_get_state', {}, function(err,j){
            if (err || !j || !j.success){
                app.innerHTML=''; var w=document.createElement('div'); w.className='airai-wrap'; var c=document.createElement('div'); c.className='airai-card notice-box notice-error'; c.textContent='Failed to load plugin state.'; w.appendChild(c); app.appendChild(w); return;
            }
            app.innerHTML=''; buildUI(j.data);
        });
    });
})();
