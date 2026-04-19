/*! AI Readiness Advisor Wizard v1 */
(function () {
    'use strict';

    function env() {
        return window.AIRAI_WIZARD || {
            ajaxurl: '',
            nonce: '',
            settings: {},
            policies: {},
            i18n: { loading: 'Loading wizard...', error: 'The wizard could not be loaded.' },
            images: {}
        };
    }

    function root() {
        return document.querySelector('#airai-wizard-app');
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
            } else if (key === 'attrs') {
                Object.keys(value || {}).forEach(function (attr) {
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

    function clear(node) {
        while (node.firstChild) {
            node.removeChild(node.firstChild);
        }
    }

    function ajax(action, data, cb) {
        var e = env();
        var form = new FormData();
        form.append('action', action);
        form.append('_ajax_nonce', e.nonce || '');

        data = data || {};
        Object.keys(data).forEach(function (k) {
            if (typeof data[k] === 'object' && data[k] !== null) {
                Object.keys(data[k]).forEach(function (sub) {
                    form.append(k + '[' + sub + ']', data[k][sub]);
                });
            } else {
                form.append(k, data[k]);
            }
        });

        fetch(e.ajaxurl, {
            method: 'POST',
            credentials: 'same-origin',
            body: form
        })
            .then(function (r) { return r.json(); })
            .then(function (j) { cb(null, j); })
            .catch(function (err) { cb(err); });
    }

    function makeOptionCard(title, body, checked, value, name, onchange) {
        var input = el('input', {
            type: 'radio',
            name: name,
            value: value,
            checked: checked
        });

        input.addEventListener('change', onchange);

        return el('label', { className: 'airai-wizard-card airai-wizard-choice' }, [
            input,
            el('strong', { text: title }),
            el('p', { text: body })
        ]);
    }

    function humanPolicy(policy) {
        if (!policy) return null;

        return el('div', { className: 'airai-wizard-card' }, [
            el('h3', { text: policy.label || '' }),
            el('p', { text: policy.summary || '' }),
            el('p', { className: 'description', text: 'Best for: ' + (policy.best_for || '') }),
            el('p', { className: 'description', text: 'Tradeoff: ' + (policy.tradeoff || '') }),
            el('p', { text: policy.explainer || '' }),
            el('h4', { text: 'Generated robots.txt snippet' }),
            el('pre', {}, [el('code', { text: policy.robots_text || '' })])
        ]);
    }

    function pager(state, setStep, applyPolicy, resetWizard) {
        var box = el('div', { className: 'airai-wizard-pager' });

        var back = el('button', {
            type: 'button',
            className: 'button',
            text: 'Back',
            disabled: state.step <= 0,
            onclick: function () {
                if (state.step > 0) setStep(state.step - 1);
            }
        });

        box.appendChild(back);

        if (state.step === 4) {
            box.appendChild(el('button', {
                type: 'button',
                className: 'button button-primary',
                text: 'Apply Recommended Policy',
                onclick: applyPolicy
            }));
        } else if (state.step === 5) {
            box.appendChild(el('button', {
                type: 'button',
                className: 'button',
                text: 'Run Wizard Again',
                onclick: resetWizard
            }));
        } else if (state.step === 3) {
            // Step 3 uses its own button.
        } else {
            box.appendChild(el('button', {
                type: 'button',
                className: 'button button-primary',
                text: 'Next',
                onclick: function () {
                    if (state.step < 5) {
                        setStep(state.step + 1);
                    }
                }
            }));
        }

        return box;
    }

    function progress(step) {
        var total = 6;
        return el('div', { className: 'airai-wizard-progress' }, [
            el('div', { className: 'airai-wizard-progress-bar' }, [
                el('span', {
                    attrs: { style: 'width:' + Math.round(((step + 1) / total) * 100) + '%' }
                })
            ]),
            el('p', { className: 'description', text: 'Step ' + (step + 1) + ' of ' + total })
        ]);
    }

    function styleOnce() {
        if (document.getElementById('airai-wizard-inline-style')) return;

        var style = document.createElement('style');
        style.id = 'airai-wizard-inline-style';
        style.textContent = [
            '.airai-wizard-wrap{max-width:980px;background:#fff;padding:20px;border-radius:12px;box-shadow:0 1px 2px rgba(0,0,0,.06)}',
            '.airai-wizard-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}',
            '.airai-wizard-card{border:1px solid #dcdcde;border-radius:12px;padding:16px;background:#fff}',
            '.airai-wizard-choice{display:block;cursor:pointer}',
            '.airai-wizard-choice input{margin-right:8px}',
            '.airai-wizard-pager{display:flex;gap:10px;justify-content:space-between;margin-top:20px}',
            '.airai-wizard-progress{margin-bottom:18px}',
            '.airai-wizard-progress-bar{height:10px;background:#e5e7eb;border-radius:999px;overflow:hidden}',
            '.airai-wizard-progress-bar span{display:block;height:10px;background:#0ea5e9}',
            '.airai-wizard-hero{display:flex;gap:18px;align-items:center;margin-bottom:18px}',
            '.airai-wizard-hero img{max-width:120px;height:auto}',
            '.airai-wizard-highlight{background:#f0f9ff;border-left:4px solid #0ea5e9;padding:12px;border-radius:8px;margin:12px 0}'
        ].join('');
        document.head.appendChild(style);
    }

    function init() {
        var app = root();
        if (!app) return;

        styleOnce();
        clear(app);
        app.appendChild(el('div', { text: env().i18n.loading || 'Loading wizard...' }));

        ajax('airai_get_wizard_state', {}, function (err, json) {
            if (err || !json || !json.success || !json.data) {
                clear(app);
                app.appendChild(el('div', { className: 'notice notice-error', text: env().i18n.error || 'The wizard could not be loaded.' }));
                return;
            }

            var data = json.data;
            var state = {
                step: 0,
                audit: data.audit || {},
                policies: data.policies || {},
                settings: data.settings || {},
                answers: (data.settings && data.settings.answers) || {},
                recommended: (data.settings && data.settings.recommended) || 'balanced',
                applied: (data.settings && data.settings.active_policy) || '',
                status: ''
            };

            function setStep(step) {
                state.step = step;
                render();
            }

            function saveAnswersAndRecommend(done) {
                ajax('airai_save_wizard_answers', { answers: state.answers }, function (saveErr, saveJson) {
                    if (!saveErr && saveJson && saveJson.success && saveJson.data) {
                        state.recommended = saveJson.data.recommended || 'balanced';
                    }
                    if (typeof done === 'function') done();
                });
            }

            function applyPolicy() {
                ajax('airai_apply_wizard_policy', { policy: state.recommended }, function (applyErr, applyJson) {
                    if (applyErr || !applyJson || !applyJson.success || !applyJson.data) {
                        state.status = 'The policy could not be applied.';
                        render();
                        return;
                    }
                    state.applied = applyJson.data.active_policy || state.recommended;
                    state.status = applyJson.data.message || 'Policy applied.';
                    state.step = 5;
                    render();
                });
            }

            function resetWizard() {
                ajax('airai_reset_wizard', {}, function () {
                    state.step = 0;
                    state.answers = {};
                    state.recommended = 'balanced';
                    state.applied = '';
                    state.status = '';
                    render();
                });
            }

            function wizardStep() {
                var hero = el('div', { className: 'airai-wizard-hero' });

                if (env().images && env().images.helper) {
                    hero.appendChild(el('img', { src: env().images.helper, alt: '' }));
                }

                hero.appendChild(el('div', {}, [
                    el('h2', { text: 'AI Readiness Setup Wizard' }),
                    el('p', { text: 'This guided setup will explain your current posture, ask about your goals, and recommend the policy that best fits your site.' })
                ]));

              return el('div', {}, [
    el('h2', { text: 'Welcome to the AI Readiness Setup Wizard' }),

    el('p', {
        text: 'This guided setup will help you understand how AI systems interact with your website and assist you in choosing the right access policy based on your goals.'
    }),

    el('div', { className: 'airai-wizard-highlight' }, [
        el('strong', { text: 'What you will do in this wizard' }),
        el('ul', {}, [
            el('li', { text: 'Review your current website configuration' }),
            el('li', { text: 'Learn what different AI access options mean' }),
            el('li', { text: 'Answer a few simple questions about your goals' }),
            el('li', { text: 'Apply a recommended policy safely through WordPress' })
        ])
    ]),

    el('p', {
        className: 'description',
        text: 'This does not change any files directly. Everything is applied safely using WordPress settings.'
    })
]);

                if (state.step === 1) {
                    return el('div', {}, [
                        el('h2', { text: 'What we found' }),
                        el('div', { className: 'airai-wizard-grid' }, [
                            el('div', { className: 'airai-wizard-card' }, [
                                el('strong', { text: 'Readiness score' }),
                                el('p', { text: String(state.audit.readiness_score || 0) + '%' })
                            ]),
                            el('div', { className: 'airai-wizard-card' }, [
                                el('strong', { text: 'Current posture' }),
                                el('p', { text: state.audit.posture || 'Unclear' })
                            ]),
                            el('div', { className: 'airai-wizard-card' }, [
                                el('strong', { text: 'robots.txt detected' }),
                                el('p', { text: state.audit.robots_present ? 'Yes' : 'No' })
                            ]),
                            el('div', { className: 'airai-wizard-card' }, [
                                el('strong', { text: 'Sitemap detected' }),
                                el('p', { text: state.audit.sitemap_present ? 'Yes' : 'No' })
                            ])
                        ]),
                        el('div', { className: 'airai-wizard-card' }, [
                            el('h3', { text: 'What this means' }),
                            el('ul', {}, (state.audit.explainers || []).map(function (item) {
                                return el('li', { text: item });
                            }))
                        ])
                    ]);
                }

                if (state.step === 2) {
                    var policies = state.policies;
                    return el('div', {}, [
                        el('h2', { text: 'Learn the choices' }),
                        el('div', { className: 'airai-wizard-grid' }, Object.keys(policies).map(function (slug) {
                            var p = policies[slug];
                            return el('div', { className: 'airai-wizard-card' }, [
                                el('h3', { text: p.label || slug }),
                                el('p', { text: p.summary || '' }),
                                el('p', { className: 'description', text: 'Best for: ' + (p.best_for || '') }),
                                el('p', { className: 'description', text: 'Tradeoff: ' + (p.tradeoff || '') }),
                                el('p', { text: p.explainer || '' })
                            ]);
                        }))
                    ]);
                }

                if (state.step === 3) {
                    return el('div', {}, [
                        el('h2', { text: 'Tell us about your goals' }),
                        el('div', { className: 'airai-wizard-card' }, [
                            el('h3', { text: 'What matters most to you?' }),
                            makeOptionCard('Maximum visibility', 'I want my site broadly discoverable in AI-assisted search and related systems.', state.answers.primary_goal === 'maximum_visibility', 'maximum_visibility', 'primary_goal', function () {
                                state.answers.primary_goal = 'maximum_visibility';
                            }),
                            makeOptionCard('Balanced visibility and control', 'I want visibility, but with more control over automated access.', state.answers.primary_goal === 'balanced_control', 'balanced_control', 'primary_goal', function () {
                                state.answers.primary_goal = 'balanced_control';
                            }),
                            makeOptionCard('User-requested access only', 'I want access mainly when a user explicitly asks for it.', state.answers.primary_goal === 'user_requested_only', 'user_requested_only', 'primary_goal', function () {
                                state.answers.primary_goal = 'user_requested_only';
                            }),
                            makeOptionCard('Block AI-related access', 'I want the strongest policy signal to block AI-related crawler access.', state.answers.primary_goal === 'block_ai', 'block_ai', 'primary_goal', function () {
                                state.answers.primary_goal = 'block_ai';
                            })
                        ]),
                        el('div', { className: 'airai-wizard-grid' }, [
                            el('div', { className: 'airai-wizard-card' }, [
                                el('h3', { text: 'Which sounds most like your site?' }),
                                el('select', {
                                    value: state.answers.site_type || '',
                                    onchange: function (e) {
                                        state.answers.site_type = e.target.value;
                                    }
                                }, [
                                    el('option', { value: '', text: 'Choose one...' }),
                                    el('option', { value: 'marketing', text: 'Marketing / lead generation' }),
                                    el('option', { value: 'local_business', text: 'Local business' }),
                                    el('option', { value: 'professional_services', text: 'Professional services' }),
                                    el('option', { value: 'blog', text: 'Blog / publishing' }),
                                    el('option', { value: 'premium', text: 'Premium or subscription content' }),
                                    el('option', { value: 'sensitive', text: 'Sensitive or privacy-focused content' })
                                ])
                            ]),
                            el('div', { className: 'airai-wizard-card' }, [
                                el('h3', { text: 'How cautious do you want to be?' }),
                                el('select', {
                                    value: state.answers.caution_level || '',
                                    onchange: function (e) {
                                        state.answers.caution_level = e.target.value;
                                    }
                                }, [
                                    el('option', { value: '', text: 'Choose one...' }),
                                    el('option', { value: 'low', text: 'Low caution' }),
                                    el('option', { value: 'medium', text: 'Medium caution' }),
                                    el('option', { value: 'high', text: 'High caution' })
                                ])
                            ])
                        ]),
                        el('button', {
                            type: 'button',
                            className: 'button button-primary',
                            text: 'Get Recommendation',
                            onclick: function () {
                                saveAnswersAndRecommend(function () {
                                    state.step = 4;
                                    render();
                                });
                            }
                        })
                    ]);
                }

                if (state.step === 4) {
                    return el('div', {}, [
                        el('h2', { text: 'Recommended policy' }),
                        humanPolicy(state.policies[state.recommended]),
                        el('div', { className: 'airai-wizard-highlight' }, [
                            el('strong', { text: 'Why this recommendation?' }),
                            el('p', { text: 'This recommendation is based on the goals, site type, and caution level you selected. You can apply it now or run the wizard again later if your needs change.' })
                        ])
                    ]);
                }

                return el('div', {}, [
                    el('h2', { text: 'Setup complete' }),
                    el('p', { text: state.status || 'Your AI access policy is now active.' }),
                    state.applied ? humanPolicy(state.policies[state.applied]) : null,
                    el('div', { className: 'airai-wizard-highlight' }, [
                        el('strong', { text: 'What happens now?' }),
                        el('p', { text: 'Your selected policy is applied through WordPress dynamic robots.txt output. You can return to the main plugin screens to review your current posture, or run the wizard again later.' })
                    ])
                ]);
            }

            function render() {
                clear(app);

                var wrap = el('div', { className: 'airai-wizard-wrap' });
                wrap.appendChild(progress(state.step));
                wrap.appendChild(wizardStep());
                wrap.appendChild(pager(state, setStep, applyPolicy, resetWizard));

                app.appendChild(wrap);
            }

            render();
        });
    }

    document.addEventListener('DOMContentLoaded', init);
})();
