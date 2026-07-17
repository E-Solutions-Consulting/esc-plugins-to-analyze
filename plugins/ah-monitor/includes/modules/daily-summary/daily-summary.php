<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class AH_Daily_Summary {

    /**
     * Register AJAX and admin menu hooks.
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu' ], 75 );
        add_action( 'wp_ajax_wcds_get_summary', [ $this, 'ajax_get_summary' ] );
    }

    /**
     * Register the submenu page under the parent BH Tools menu.
     */
    public function register_menu() {
        add_submenu_page(
            PARENT_MENU_SLUG,
            'Daily Summary',
            'Daily Summary',
            'manage_options',
            PARENT_MENU_SLUG . '--daily-summary',
            [ $this, 'render_page' ],
            'dashicons-chart-bar',
            56
        );
    }

    /**
     * Render the full dashboard page with inline styles, HTML and JS.
     */
    public function render_page() {
        $today = date('Y-m-d');
        $nonce = wp_create_nonce('wcds_nonce');
        $ajax  = admin_url('admin-ajax.php');
        ?>
        <style>
        *{box-sizing:border-box}
        #wcds{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;max-width:96%;padding:20px 10px;color:#111}
        #wcds h1{font-size:22px;font-weight:700;margin:0 0 4px}
        .wcds-top{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px}
        .wcds-top label{font-size:11px;font-weight:700;text-transform:uppercase;color:#888;display:block;margin-bottom:4px}
        #wcds-date{padding:7px 12px;border:1.5px solid #ddd;border-radius:8px;font-size:14px}
        #wcds-btn{padding:8px 18px;background:#1d4ed8;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer}
        #wcds-btn:hover{background:#1e40af}
        .health-bar{display:flex;align-items:center;justify-content:space-between;padding:10px 16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:16px;flex-wrap:wrap;gap:10px}
        .health-left{display:flex;align-items:center;gap:10px}
        .pulse{width:9px;height:9px;border-radius:50%;background:#16a34a;position:relative;flex-shrink:0}
        .pulse::after{content:'';position:absolute;inset:-3px;border-radius:50%;background:#22c55e;opacity:.35;animation:pulse-anim 2s ease-in-out infinite}
        .pulse.warn{background:#d97706}.pulse.warn::after{background:#f59e0b}
        .pulse.bad{background:#dc2626}.pulse.bad::after{background:#ef4444}
        @keyframes pulse-anim{0%,100%{transform:scale(1);opacity:.35}50%{transform:scale(1.7);opacity:0}}
        .health-label{font-size:13px;font-weight:600;color:#111}
        .health-sub{font-size:11px;color:#888}
        .health-stats{display:flex;gap:20px;flex-wrap:wrap}
        .hstat{text-align:right}
        .hstat-n{font-size:13px;font-weight:700;color:#111}
        .hstat-l{font-size:10px;color:#aaa}
        .kpi-grid{display:grid;gap:12px;margin-bottom:14px}
        .kpi-grid.col-2{grid-template-columns:repeat(2,1fr)}
        .kpi-grid.col-4{grid-template-columns:repeat(4,1fr)}
        .kpi-hero{background:#fff;border:1.5px solid #e5e7eb;border-radius:12px;padding:16px;position:relative;overflow:hidden}
        .kpi-hero .accent{height:3px;border-radius:3px;margin-bottom:12px}
        .kpi-hero-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#888;margin-bottom:6px}
        .kpi-hero-val{font-size:30px;font-weight:800;line-height:1;margin-bottom:3px}
        .kpi-hero-sub{font-size:11px;color:#aaa;margin-bottom:10px}
        .kpi-split{display:flex;gap:10px;padding-top:10px;border-top:1.5px solid #f3f4f6}
        .ks-item{flex:1}.ks-item .ks-l{font-size:10px;color:#aaa}.ks-item .ks-v{font-size:13px;font-weight:700;color:#111}
        .kpi-surf{background:#f8fafc;border-radius:10px;padding:12px 14px}
        .kpi-surf .kl{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#888;margin-bottom:5px}
        .kpi-surf .kv{font-size:20px;font-weight:800;color:#111;line-height:1}
        .kpi-surf .ks{font-size:11px;color:#aaa;margin-top:3px}
        .kv.green{color:#15803d}.kv.amber{color:#b45309}.kv.red{color:#dc2626}.kv.blue{color:#1d4ed8}
        .panel{background:#fff;border:1.5px solid #e5e7eb;border-radius:12px;overflow:hidden;margin-bottom:14px}
        .panel-grid{display:grid;gap:14px;margin-bottom:14px}
        .panel-grid.col-2{grid-template-columns:repeat(2,1fr)}
        .panel-grid.col-3{grid-template-columns:repeat(3,1fr)}
        .panel-grid.col-23{grid-template-columns:2fr 3fr}
        .ph{padding:11px 16px;border-bottom:1.5px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px}
        .ph-title{font-size:12px;font-weight:700;color:#111}
        .ph-meta{font-size:11px;color:#aaa;background:#f8fafc;padding:2px 8px;border-radius:20px}
        .pb{padding:12px 16px}
        .sr{display:flex;align-items:center;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f5f5f8}
        .sr:last-child{border:none}
        .sr-l{display:flex;align-items:center;gap:7px;font-size:12px;color:#333}
        .sr-r{display:flex;align-items:center;gap:8px}
        .dot{width:6px;height:6px;border-radius:50%;flex-shrink:0}
        .cnt{font-size:11px;font-weight:700;background:#f5f5f8;padding:1px 7px;border-radius:20px;color:#555}
        .tot{font-size:12px;font-weight:700;color:#111;min-width:72px;text-align:right}
        .prog-wrap{height:5px;background:#e5e7eb;border-radius:3px;overflow:hidden;margin-top:5px}
        .prog-bar{height:5px;border-radius:3px;transition:width .4s}
        .rf-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;padding:14px 16px}
        .rf-card{background:#f8fafc;border-radius:10px;padding:12px 14px}
        .rf-lbl{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#888;margin-bottom:5px}
        .rf-val{font-size:26px;font-weight:800;line-height:1;color:#111}
        .rf-sub{font-size:11px;color:#aaa;margin-top:3px}
        .rf-cols{display:grid;grid-template-columns:1fr 1fr;gap:0;padding:0 16px 14px}
        .rf-col-title{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#888;margin-bottom:6px;padding:8px 0 4px;border-top:1px solid #f3f4f6}
        .alert-strip{display:flex;align-items:flex-start;gap:10px;padding:10px 16px;border-top:1px solid #f3f4f6;font-size:12px;line-height:1.5}
        .alert-strip.ok{background:#f0fdf4;color:#15803d}
        .alert-strip.warn{background:#fffbeb;color:#92400e}
        .alert-strip.bad{background:#fef2f2;color:#991b1b}
        .pending-hours{display:flex;flex-wrap:wrap;gap:6px;padding:10px 16px;border-top:1px solid #f3f4f6}
        .ph-pill{background:#eff6ff;color:#1d4ed8;font-size:11px;font-weight:700;padding:3px 9px;border-radius:20px}
        .chart-wrap{position:relative;width:100%;height:180px}
        table.pt{width:100%;border-collapse:collapse;font-size:12px;table-layout:fixed}
        table.pt thead th{text-align:left;padding:7px 14px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#888;background:#fafafa;border-bottom:1.5px solid #f3f4f6;white-space:nowrap}
        table.pt tbody tr{border-bottom:1px solid #f5f5f8}
        table.pt tbody tr:hover{background:#fafbff}
        table.pt tbody td{padding:8px 14px;color:#333;vertical-align:middle}
        .state-row{padding:7px 0;border-bottom:1px solid #f5f5f8}
        .state-row:last-child{border:none}
        .state-top{display:flex;justify-content:space-between;margin-bottom:4px}
        .state-name{font-size:12px;color:#111}
        .state-total{font-size:12px;font-weight:700;color:#111}
        .ci{display:flex;align-items:center;justify-content:space-between;padding:7px 0;border-bottom:1px solid #f5f5f8}
        .ci:last-child{border:none}
        .coupon-code{font-family:monospace;font-size:11px;font-weight:700;background:#fff7ed;color:#c2410c;padding:2px 8px;border-radius:4px;border:1px solid #fed7aa}
        .mix-wrap{display:flex;align-items:center;gap:20px;padding:14px 16px}
        .mix-canvas{position:relative;width:120px;height:120px;flex-shrink:0}
        .mix-legend .mix-pct{font-size:22px;font-weight:800}
        .mix-legend .mix-lbl{font-size:11px;color:#aaa;margin-top:1px;margin-bottom:12px}
        .refresh-bar{display:flex;align-items:center;gap:10px;padding:9px 16px;background:#f8fafc;border-top:1px solid #e5e7eb;font-size:12px;color:#888}
        .refresh-bar button{display:flex;align-items:center;gap:5px;font-size:12px;color:#555;background:#fff;border:1px solid #ddd;border-radius:6px;padding:4px 10px;cursor:pointer}
        .refresh-bar button:hover{background:#f3f4f6}
        #wcds-loading{display:flex;align-items:center;gap:12px;padding:60px;color:#888;font-size:14px}
        .spinner{width:20px;height:20px;border:3px solid #eee;border-top-color:#1d4ed8;border-radius:50%;animation:spin .7s linear infinite}
        @keyframes spin{to{transform:rotate(360deg)}}
        #wcds-empty{text-align:center;padding:60px;color:#bbb;font-size:15px}
        #wcds-error{padding:12px 16px;background:#fef2f2;color:#991b1b;border-radius:8px;font-size:13px;margin-bottom:14px;display:none}
        @media(max-width:900px){
            .kpi-grid.col-4,.kpi-grid.col-2{grid-template-columns:1fr 1fr}
            .panel-grid.col-2,.panel-grid.col-3,.panel-grid.col-23{grid-template-columns:1fr}
            .rf-grid,.rf-cols{grid-template-columns:1fr}
        }
        </style>

        <div id="wcds">
            <div class="wcds-top">
                <h1>📊 Daily Orders Summary</h1>
                <div style="display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap">
                    <div>
                        <label>Date</label>
                        <input type="date" id="wcds-date" value="<?php echo esc_attr($today); ?>">
                    </div>
                    <button id="wcds-btn">↻ Refresh</button>
                </div>
            </div>

            <div id="wcds-error"></div>
            <div id="wcds-loading" style="display:none"><div class="spinner"></div><span>Loading data...</span></div>
            <div id="wcds-empty" style="display:none">📭 No orders found for this date.</div>

            <div id="wcds-content" style="display:none">

                <div class="health-bar" id="wcds-health-bar">
                    <div class="health-left">
                        <div class="pulse" id="health-pulse"></div>
                        <div>
                            <div class="health-label" id="health-txt">Checkout operational</div>
                            <div class="health-sub" id="health-sub">—</div>
                        </div>
                    </div>
                    <div class="health-stats">
                        <div class="hstat"><div class="hstat-n" id="h-last-hr">—</div><div class="hstat-l">last hour</div></div>
                        <div class="hstat"><div class="hstat-n" id="h-fail-rate">—</div><div class="hstat-l">failure rate</div></div>
                        <div class="hstat"><div class="hstat-n" id="h-pace">—</div><div class="hstat-l">orders/hour</div></div>
                    </div>
                </div>

                <div class="kpi-grid col-2">
                    <div class="kpi-hero">
                        <div class="accent" style="background:#15803d"></div>
                        <div class="kpi-hero-label">💰 Real revenue (today)</div>
                        <div class="kpi-hero-val green" id="kpi-revenue">$0.00</div>
                        <div class="kpi-hero-sub" id="kpi-rev-sub">collected orders today</div>
                        <div class="kpi-split">
                            <div class="ks-item"><div class="ks-l">Renewals</div><div class="ks-v" id="kpi-ren-rev">—</div></div>
                            <div class="ks-item"><div class="ks-l">New (captured)</div><div class="ks-v" id="kpi-new-rev">—</div></div>
                            <div class="ks-item"><div class="ks-l">EOD projection</div><div class="ks-v amber" id="kpi-proj">—</div></div>
                        </div>
                    </div>
                    <div class="kpi-hero">
                        <div class="accent" style="background:#1d4ed8"></div>
                        <div class="kpi-hero-label">⏳ Stripe on hold (pending capture)</div>
                        <div class="kpi-hero-val blue" id="kpi-stripe">$0.00</div>
                        <div class="kpi-hero-sub">captured when order reaches Completed</div>
                        <div class="kpi-split">
                            <div class="ks-item"><div class="ks-l">New orders</div><div class="ks-v" id="kpi-new-cnt">0</div></div>
                            <div class="ks-item"><div class="ks-l">Renewals</div><div class="ks-v" id="kpi-ren-cnt">0</div></div>
                            <div class="ks-item"><div class="ks-l">Total orders</div><div class="ks-v" id="kpi-total-cnt">0</div></div>
                        </div>
                    </div>
                </div>

                <div class="kpi-grid col-4" style="margin-bottom:14px">
                    <div class="kpi-surf"><div class="kl">📈 Revenue/hour (last 3h)</div><div class="kv" id="m-rev-hr">$0</div><div class="ks" id="m-rev-hr-sub">—</div></div>
                    <div class="kpi-surf"><div class="kl">✅ Completion rate (new)</div><div class="kv" id="m-comp-rate">—</div><div class="ks" id="m-comp-sub">—</div></div>
                    <div class="kpi-surf"><div class="kl">🏷️ Discounts applied</div><div class="kv amber" id="m-discount">$0.00</div><div class="ks" id="m-disc-sub">—</div></div>
                    <div class="kpi-surf"><div class="kl">⚠️ At risk</div><div class="kv" id="m-risk">0</div><div class="ks">failed + cancelled + on-hold</div></div>
                </div>

                <div class="panel">
                    <div class="ph">
                        <span class="ph-title">🔄 Today's renewals — scheduled vs created</span>
                        <span class="ph-meta" id="rf-meta">—</span>
                    </div>
                    <div class="rf-grid">
                        <div class="rf-card">
                            <div class="rf-lbl">📅 Scheduled today (AS)</div>
                            <div class="rf-val blue" id="rf-as-total">—</div>
                            <div class="rf-sub">guaranteed minimum for the day</div>
                        </div>
                        <div class="rf-card">
                            <div class="rf-lbl">⚡ Processed by AS</div>
                            <div class="rf-val" id="rf-as-complete">—</div>
                            <div class="rf-sub" id="rf-as-sub">— still pending today</div>
                            <div class="prog-wrap"><div class="prog-bar" id="rf-prog" style="width:0%;background:#1d4ed8"></div></div>
                        </div>
                        <div class="rf-card">
                            <div class="rf-lbl">🧾 Auto renewal orders created</div>
                            <div class="rf-val" id="rf-auto-total">—</div>
                            <div class="rf-sub" id="rf-auto-sub">—</div>
                        </div>
                    </div>
                    <div class="rf-cols">
                        <div>
                            <div class="rf-col-title">Action Scheduler</div>
                            <div id="rf-as-rows"></div>
                        </div>
                        <div>
                            <div class="rf-col-title">Auto renewal orders (today)</div>
                            <div id="rf-auto-rows"></div>
                        </div>
                    </div>
                    <div id="rf-pending-hours" style="display:none">
                        <div style="padding:6px 16px 4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#888">Pending by hour</div>
                        <div class="pending-hours" id="rf-pending-pills"></div>
                    </div>
                    <div id="rf-manual-block" style="display:none">
                        <div style="padding:6px 16px 4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#888">Early renewals / manual Stripe payments (additional)</div>
                        <div style="padding:0 16px 12px;font-size:12px;color:#555" id="rf-manual-txt"></div>
                    </div>
                    <div class="alert-strip warn" id="rf-alert">
                        <span id="rf-alert-txt">—</span>
                    </div>
                </div>

                <div class="panel">
                    <div class="ph">
                        <span class="ph-title">📊 Orders by hour</span>
                        <div style="display:flex;gap:14px;align-items:center">
                            <span style="display:flex;align-items:center;gap:4px;font-size:11px;color:#888"><span style="width:8px;height:8px;border-radius:2px;background:#2563eb;display:inline-block"></span>New</span>
                            <span style="display:flex;align-items:center;gap:4px;font-size:11px;color:#888"><span style="width:8px;height:8px;border-radius:2px;background:#16a34a;display:inline-block"></span>Renewals</span>
                            <span style="display:flex;align-items:center;gap:4px;font-size:11px;color:#888"><span style="width:8px;height:8px;border-radius:1px;border:1.5px dashed #aaa;display:inline-block"></span>Average</span>
                        </div>
                    </div>
                    <div class="pb"><div class="chart-wrap"><canvas id="ch-hourly" role="img" aria-label="Stacked bar chart of orders per hour split by new and renewals">Orders per hour data.</canvas></div></div>
                </div>

                <div class="panel-grid col-23">
                    <div style="display:flex;flex-direction:column;gap:14px">
                        <div class="panel">
                            <div class="ph"><span class="ph-title">⚡ New orders — by status</span><span class="ph-meta" id="badge-new">—</span></div>
                            <div style="padding:6px 16px" id="wcds-new-statuses"></div>
                        </div>
                        <div class="panel">
                            <div class="ph"><span class="ph-title">🔄 Renewals — by status</span><span class="ph-meta" id="badge-ren">—</span></div>
                            <div style="padding:6px 16px" id="wcds-ren-statuses"></div>
                        </div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:14px">
                        <div class="panel">
                            <div class="ph"><span class="ph-title">📋 All orders — by status</span><span class="ph-meta" id="badge-all">—</span></div>
                            <div style="padding:6px 16px" id="wcds-all-statuses"></div>
                        </div>
                        <div class="panel">
                            <div class="ph"><span class="ph-title">💳 Stripe</span><span class="ph-meta" id="stripe-meta">—</span></div>
                            <div style="padding:6px 16px" id="wcds-stripe-detail"></div>
                        </div>
                    </div>
                </div>

                <div class="panel-grid col-2">
                    <div class="panel">
                        <div class="ph"><span class="ph-title">🥧 New vs renewal mix</span></div>
                        <div class="mix-wrap">
                            <div class="mix-canvas"><canvas id="ch-mix" role="img" aria-label="Donut chart showing proportion of new vs renewal orders">Order type mix.</canvas></div>
                            <div class="mix-legend" id="mix-legend"></div>
                        </div>
                    </div>
                    <div class="panel">
                        <div class="ph"><span class="ph-title">🏷️ Coupons</span></div>
                        <div style="padding:6px 16px" id="wcds-coupons"></div>
                    </div>
                </div>

                <div class="panel-grid col-2">
                    <div class="panel">
                        <div class="ph"><span class="ph-title">🗺️ Purchases by state (US)</span><span class="ph-meta" id="badge-states">—</span></div>
                        <div style="padding:6px 16px" id="wcds-states"></div>
                    </div>
                    <div class="panel">
                        <div class="ph"><span class="ph-title">📦 Products sold</span><span class="ph-meta" id="badge-prods">—</span></div>
                        <div style="overflow-x:auto">
                            <table class="pt">
                                <thead><tr>
                                    <th style="width:40%">Product</th>
                                    <th style="text-align:center;width:10%">Qty</th>
                                    <th style="text-align:right;width:18%">Total</th>
                                    <th style="text-align:center;width:16%">⚡ New</th>
                                    <th style="text-align:center;width:16%">🔄 Renew.</th>
                                </tr></thead>
                                <tbody id="wcds-prod-tbody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="refresh-bar">
                    <button onclick="manualRefresh()">↻ Refresh now</button>
                    <span id="wcds-updated">—</span>
                    <span style="margin-left:auto;font-size:11px;color:#bbb">No auto-refresh — update manually when needed</span>
                </div>

            </div>
        </div>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
        <script>
        (function(){
            var hourlyChart = null, mixChart = null;
            var $ = function(id){ return document.getElementById(id); };
            var set = function(id,v){ var e=$(id); if(e) e.textContent=v; };
            var html = function(id,h){ var e=$(id); if(e) e.innerHTML=h; };
            var show = function(id){ var e=$(id); if(e) e.style.display=''; };
            var hide = function(id){ var e=$(id); if(e) e.style.display='none'; };

            function fmt(n){ return '$'+parseFloat(n||0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,','); }
            function fmtK(n){ n=parseFloat(n||0); return n>=1000?'$'+(n/1000).toFixed(1)+'k':'$'+n.toFixed(0); }

            var STATUS_COLORS = {
                'completed':'#16a34a','processing':'#2563eb','on-hold':'#d97706',
                'cancelled':'#dc2626','failed':'#dc2626','refunded':'#6b7280',
                'pending':'#94a3b8','send_to_telegra':'#7c3aed','waiting_room':'#0891b2',
                'provider_review':'#0891b2','collect_payment':'#d97706','error_review':'#b45309',
                'prerequisites':'#7c3aed','admin_review':'#0369a1'
            };

            function statusColor(slug){
                var clean = slug.replace(/^wc-/,'');
                return STATUS_COLORS[clean] || STATUS_COLORS[slug] || '#94a3b8';
            }

            function load(){
                var date = $('wcds-date').value;
                if(!date) return;
                hide('wcds-content');
                hide('wcds-empty');
                $('wcds-error').style.display='none';
                show('wcds-loading');

                var fd = new FormData();
                fd.append('action','wcds_get_summary');
                fd.append('nonce','<?php echo esc_js($nonce); ?>');
                fd.append('date', date);

                fetch('<?php echo esc_js($ajax); ?>', {method:'POST',body:fd})
                    .then(function(r){ return r.json(); })
                    .then(function(res){
                        hide('wcds-loading');
                        if(!res.success){
                            $('wcds-error').style.display='';
                            $('wcds-error').textContent='Error: '+(res.data||'unknown');
                            return;
                        }
                        var d = res.data;
                        if(d.empty){ show('wcds-empty'); return; }
                        render(d);
                        show('wcds-content');
                        var now = new Date();
                        set('wcds-updated','Updated: '+now.getHours().toString().padStart(2,'0')+':'+now.getMinutes().toString().padStart(2,'0'));
                    })
                    .catch(function(e){
                        hide('wcds-loading');
                        $('wcds-error').style.display='';
                        $('wcds-error').textContent='Connection error: '+e.message;
                    });
            }

            function render(d){
                renderHealth(d);
                renderHeroKPIs(d);
                renderSubKPIs(d);
                renderRenewalForecast(d.renewal_forecast);
                renderHourly(d);
                renderStatusRows('wcds-new-statuses', d.type_statuses.new, true);
                renderStatusRows('wcds-ren-statuses', d.type_statuses.renewal, true);
                renderStatusRows('wcds-all-statuses', d.statuses, false);
                renderStripe(d);
                renderMix(d);
                renderCoupons(d);
                renderStates(d.state_detail||[]);
                renderProducts(d.products_summary||[]);
                set('badge-new', fmt((d.payment_types.new||{}).total||0)+' · '+(d.payment_types.new||{}).count+' orders');
                set('badge-ren', fmt((d.payment_types.renewal||{}).total||0)+' · '+(d.payment_types.renewal||{}).count+' orders');
                set('badge-all', d.total_orders+' orders');
                set('stripe-meta', (d.stripe_orders||[]).length+' payments');
            }

            function renderHealth(d){
                var now = new Date();
                var curHour = now.getHours();
                var elapsed = Math.max(1, curHour+(now.getMinutes()/60));
                var nt = d.payment_types.new||{}, rt = d.payment_types.renewal||{};
                var newCnt = nt.count||0, renCnt = rt.count||0;
                var lastHrNew = (d.hourly&&d.hourly.new&&curHour>0) ? (d.hourly.new[curHour-1]||0) : 0;
                var lastHrRen = (d.hourly&&d.hourly.ren&&curHour>0) ? (d.hourly.ren[curHour-1]||0) : 0;
                var failRate = d.total_orders>0 ? ((d.failed_orders||0)/d.total_orders*100).toFixed(1)+'%' : '0%';
                var pace = d.total_orders>0 ? (d.total_orders/elapsed).toFixed(1) : 0;
                var pulse = $('health-pulse');
                if(parseFloat(failRate)>20){
                    pulse.className='pulse bad';
                    set('health-txt','⚠️ High failure rate — review urgently');
                } else if(parseFloat(failRate)>10){
                    pulse.className='pulse warn';
                    set('health-txt','Checkout active — review failed orders');
                } else {
                    pulse.className='pulse';
                    set('health-txt','Checkout operational');
                }
                set('health-sub', newCnt+' new · '+renCnt+' renewals');
                set('h-last-hr', lastHrNew+lastHrRen);
                set('h-fail-rate', failRate);
                set('h-pace', pace);
            }

            function renderHeroKPIs(d){
                var now = new Date();
                var elapsed = Math.max(1, now.getHours()+(now.getMinutes()/60));
                set('kpi-revenue', fmt(d.total_revenue));
                set('kpi-rev-sub', 'collected orders today');
                set('kpi-ren-rev', fmt((d.payment_types.renewal||{}).total||0));
                set('kpi-new-rev', fmt(d.new_revenue_captured||0));
                set('kpi-proj', '~'+fmtK((d.total_revenue/elapsed)*24));
                set('kpi-stripe', fmt(d.stripe_capture||0));
                set('kpi-new-cnt', (d.payment_types.new||{}).count||0);
                set('kpi-ren-cnt', (d.payment_types.renewal||{}).count||0);
                set('kpi-total-cnt', d.total_orders);
            }

            function renderSubKPIs(d){
                var now = new Date();
                var curHour = now.getHours();
                var last3new=0, last3ren=0;
                for(var i=Math.max(0,curHour-3);i<curHour;i++){
                    last3new += (d.hourly&&d.hourly.new ? d.hourly.new[i]||0 : 0);
                    last3ren += (d.hourly&&d.hourly.ren ? d.hourly.ren[i]||0 : 0);
                }
                var avgOrderVal = d.total_orders>0 ? d.total_revenue/d.total_orders : 0;
                set('m-rev-hr', fmtK(avgOrderVal*(last3new+last3ren)/3));
                set('m-rev-hr-sub', 'last 3h ('+last3new+' new, '+last3ren+' renewals)');
                var newCnt = (d.payment_types.new||{}).count||0;
                var newCompleted = d.new_completed||0;
                var compRate = newCnt>0 ? Math.round(newCompleted/newCnt*100) : 0;
                var crEl = $('m-comp-rate');
                crEl.textContent = compRate+'%';
                crEl.className = 'kv '+(compRate>=70?'green':compRate>=50?'amber':'red');
                set('m-comp-sub', newCompleted+' of '+newCnt+' new orders completed');
                set('m-discount', fmt(d.total_discount));
                set('m-disc-sub', (d.coupons||[]).length+' different coupons');
                var risk = (d.failed_orders||0)+(d.cancelled_orders||0)+(d.on_hold_orders||0);
                var riskEl = $('m-risk');
                riskEl.textContent = risk;
                riskEl.className = 'kv '+(risk>10?'red':risk>3?'amber':'green');
            }

            function renderRenewalForecast(rf){
                if(!rf) return;
                var pct = rf.as_pct||0;
                set('rf-meta', rf.as_total+' scheduled · '+pct+'% processed');
                set('rf-as-total', rf.as_total);
                var completeEl = $('rf-as-complete');
                completeEl.textContent = rf.as_complete;
                completeEl.className = 'rf-val '+(pct>=80?'green':pct>=50?'blue':'amber');
                set('rf-as-sub', rf.as_pending+' pending · '+rf.as_failed+' AS failed');
                var prog = $('rf-prog');
                prog.style.width = pct+'%';
                prog.style.background = pct>=80?'#16a34a':pct>=50?'#2563eb':'#d97706';
                set('rf-auto-total', rf.auto_total);
                set('rf-auto-sub', rf.auto_success+' successful · '+rf.auto_failed+' failed/cancelled · '+fmt(rf.auto_revenue));
                var asRows = [
                    {label:'Completed by AS', cnt:rf.as_complete, color:'#16a34a'},
                    {label:'Pending today',   cnt:rf.as_pending,  color:'#2563eb'},
                    {label:'AS failed',       cnt:rf.as_failed,   color:'#dc2626'},
                ];
                html('rf-as-rows', asRows.map(function(r){
                    return '<div class="sr"><div class="sr-l"><div class="dot" style="background:'+r.color+'"></div>'+r.label+'</div>'
                        +'<span class="cnt">'+r.cnt+'</span></div>';
                }).join(''));
                html('rf-auto-rows', (rf.auto_by_status||[]).length ? (rf.auto_by_status||[]).map(function(r){
                    return '<div class="sr"><div class="sr-l"><div class="dot" style="background:'+statusColor(r.slug)+'"></div>'+r.label+'</div>'
                        +'<div class="sr-r"><span class="cnt">'+r.count+'</span>'
                        +(r.total?'<span class="tot">'+fmt(r.total)+'</span>':'<span class="tot" style="color:#bbb">—</span>')
                        +'</div></div>';
                }).join('') : '<p style="font-size:12px;color:#bbb;padding:8px 0">No auto renewal orders today</p>');
                if(rf.pending_by_hour && rf.pending_by_hour.length){
                    show('rf-pending-hours');
                    html('rf-pending-pills', rf.pending_by_hour.map(function(p){
                        return '<span class="ph-pill">'+p.hora_local+' · '+p.cnt+'</span>';
                    }).join(''));
                } else {
                    hide('rf-pending-hours');
                }
                if(rf.manual_total>0){
                    show('rf-manual-block');
                    set('rf-manual-txt', rf.manual_total+' orders ('+rf.manual_success+' successful · '+fmt(rf.manual_revenue)+') — early renewals or manual Stripe payments');
                } else {
                    hide('rf-manual-block');
                }
                var alertEl = $('rf-alert');
                alertEl.className = 'alert-strip '+(rf.alert_level||'warn');
                set('rf-alert-txt', rf.alert_msg||'—');
            }

            function renderStatusRows(id, rows, showTotal){
                if(!rows||!rows.length){ html(id,'<p style="font-size:12px;color:#bbb;padding:8px 0">No data</p>'); return; }
                html(id, rows.map(function(r){
                    var c = statusColor(r.slug);
                    return '<div class="sr">'
                        +'<div class="sr-l"><div class="dot" style="background:'+c+'"></div>'
                        +'<a href="'+(r.url||'#')+'" target="_blank" style="color:#333;text-decoration:none">'+r.label+'</a></div>'
                        +'<div class="sr-r"><span class="cnt">'+r.count+'</span>'
                        +(showTotal&&r.total?'<span class="tot">'+fmt(r.total)+'</span>':'<span class="tot" style="color:#bbb">—</span>')
                        +'</div></div>';
                }).join(''));
            }

            function renderHourly(d){
                if(!d.hourly) return;
                var labels=[];
                for(var i=0;i<24;i++) labels.push(i===0?'12a':i<12?i+'a':i===12?'12p':(i-12)+'p');
                var totals = labels.map(function(_,i){ return (d.hourly.new[i]||0)+(d.hourly.ren[i]||0); });
                var avg = Math.round(totals.reduce(function(a,b){return a+b},0)/24);
                if(hourlyChart) hourlyChart.destroy();
                hourlyChart = new Chart($('ch-hourly'),{
                    type:'bar',
                    data:{
                        labels:labels,
                        datasets:[
                            {label:'New',data:d.hourly.new,backgroundColor:'#2563eb',stack:'s'},
                            {label:'Renewals',data:d.hourly.ren,backgroundColor:'#16a34a',stack:'s'},
                            {label:'Average',data:labels.map(function(){return avg;}),type:'line',borderColor:'#aaa',borderWidth:1.5,borderDash:[4,3],pointRadius:0,fill:false,tension:0,stack:''},
                        ]
                    },
                    options:{
                        responsive:true,maintainAspectRatio:false,
                        plugins:{legend:{display:false},tooltip:{mode:'index'}},
                        scales:{
                            x:{grid:{display:false},ticks:{font:{size:10},color:'#aaa',maxRotation:0,autoSkip:true,maxTicksLimit:12}},
                            y:{grid:{color:'rgba(0,0,0,0.04)'},ticks:{font:{size:10},color:'#aaa',stepSize:1},beginAtZero:true}
                        }
                    }
                });
            }

            function renderStripe(d){
                var captured = d.new_revenue_captured||0;
                var pending  = d.stripe_capture||0;
                var total    = captured+pending;
                var pct      = total>0?Math.round(captured/total*100):0;
                html('wcds-stripe-detail',
                    row('Total charged in Stripe', fmt(total), '')
                    +row('Captured (completed)', fmt(captured), 'color:#15803d')
                    +row('On hold (pending)', fmt(pending), 'color:#b45309')
                    +'<div style="padding:8px 0"><div style="display:flex;justify-content:space-between;font-size:11px;color:#aaa;margin-bottom:4px">'
                    +'<span>Captured '+pct+'%</span><span>'+(100-pct)+'% pending</span></div>'
                    +'<div class="prog-wrap"><div class="prog-bar" style="width:'+pct+'%;background:#16a34a"></div></div></div>'
                );
            }

            function row(lbl,val,style){ return '<div class="sr"><span class="sr-l" style="color:#888">'+lbl+'</span><span class="tot" style="'+style+'">'+val+'</span></div>'; }

            function renderMix(d){
                var newCnt = (d.payment_types.new||{}).count||0;
                var renCnt = (d.payment_types.renewal||{}).count||0;
                var tot = newCnt+renCnt||1;
                var np = Math.round(newCnt/tot*100), rp = 100-np;
                html('mix-legend',
                    '<div class="mix-pct" style="color:#2563eb">'+np+'%</div><div class="mix-lbl">New ('+newCnt+')</div>'
                    +'<div class="mix-pct" style="color:#16a34a">'+rp+'%</div><div class="mix-lbl">Renewals ('+renCnt+')</div>'
                );
                if(mixChart) mixChart.destroy();
                mixChart = new Chart($('ch-mix'),{
                    type:'doughnut',
                    data:{labels:['New','Renewals'],datasets:[{data:[newCnt,renCnt],backgroundColor:['#2563eb','#16a34a'],borderWidth:0}]},
                    options:{responsive:true,maintainAspectRatio:false,cutout:'72%',plugins:{legend:{display:false}}}
                });
            }

            function renderCoupons(d){
                if(!d.coupons||!d.coupons.length){ html('wcds-coupons','<p style="font-size:12px;color:#bbb;padding:8px 0">No coupons today</p>'); return; }
                html('wcds-coupons', d.coupons.map(function(c){
                    return '<div class="ci"><span class="coupon-code">'+c.code+'</span>'
                        +'<div style="display:flex;align-items:center;gap:8px">'
                        +'<span style="font-size:11px;color:#aaa">×'+c.count+'</span>'
                        +'<span style="font-size:12px;font-weight:700;color:#dc2626">-'+fmt(c.discount)+'</span>'
                        +'</div></div>';
                }).join(''));
            }

            function renderStates(rows){
                set('badge-states', rows.length+' states');
                if(!rows.length){ html('wcds-states','<p style="font-size:12px;color:#bbb;padding:8px 0">No data</p>'); return; }
                var max = rows[0].total||1;
                html('wcds-states', rows.slice(0,8).map(function(r){
                    var pct = Math.round(r.total/max*100);
                    return '<div class="state-row">'
                        +'<div class="state-top">'
                        +'<span class="state-name"><strong style="font-size:11px;color:#aaa">'+r.code+'</strong> '+r.name+'</span>'
                        +'<span class="state-total">'+fmt(r.total)+' <span style="font-size:11px;font-weight:400;color:#aaa">· '+r.count+'</span></span>'
                        +'</div>'
                        +'<div class="prog-wrap"><div class="prog-bar" style="width:'+pct+'%;background:#2563eb"></div></div>'
                        +'</div>';
                }).join(''));
            }

            function renderProducts(prods){
                set('badge-prods', prods.reduce(function(s,p){return s+p.qty},0)+' units');
                if(!prods.length){ html('wcds-prod-tbody','<tr><td colspan="5" style="text-align:center;color:#bbb;padding:20px">No products</td></tr>'); return; }
                html('wcds-prod-tbody', prods.map(function(p){
                    return '<tr>'
                        +'<td style="font-size:12px;font-weight:600;color:#111">'+p.name+'</td>'
                        +'<td style="text-align:center;font-size:12px;color:#555">'+p.qty+'</td>'
                        +'<td style="text-align:right;font-size:12px;font-weight:700">'+fmt(p.total)+'</td>'
                        +'<td style="text-align:center;font-size:12px;color:#2563eb">'+(p.new_qty||'—')+'</td>'
                        +'<td style="text-align:center;font-size:12px;color:#16a34a">'+(p.ren_qty||'—')+'</td>'
                        +'</tr>';
                }).join(''));
            }

            window.manualRefresh = function(){ load(); };
            $('wcds-btn').addEventListener('click', load);
            $('wcds-date').addEventListener('change', load);
            load();
        })();
        </script>
        <?php
    }

    /**
     * Handle the AJAX request, validate nonce and permissions,
     * query HPOS orders for the requested date and return the summary payload.
     */
    public function ajax_get_summary() {
        check_ajax_referer( 'wcds_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $date  = sanitize_text_field( $_POST['date'] ?? wp_date('Y-m-d') );
        $wp_tz = wp_timezone();

        $start_local = new DateTime( $date . ' 00:00:00', $wp_tz );
        $end_local   = new DateTime( $date . ' 23:59:59', $wp_tz );
        $start_utc   = $start_local->getTimestamp();
        $end_utc     = $end_local->getTimestamp();

        $orders = wc_get_orders([
            'limit'        => -1,
            'date_created' => $start_utc . '...' . $end_utc,
            'orderby'      => 'date',
            'order'        => 'ASC',
            'type'         => 'shop_order',
        ]);

        if ( empty( $orders ) ) {
            wp_send_json_success([ 'empty' => true ]);
            return;
        }

        wp_send_json_success( $this->build_summary( $orders, $date ) );
    }

    /**
     * Build the full summary payload from a list of WC_Order objects.
     *
     * @param WC_Order[] $orders
     * @param string     $date   Y-m-d in WP local timezone.
     * @return array
     */
    private function build_summary( array $orders, string $date ): array {
        $total_revenue        = 0;
        $total_discount       = 0;
        $new_revenue_captured = 0.0;
        $new_completed        = 0;
        $failed_orders        = 0;
        $cancelled_orders     = 0;
        $on_hold_orders       = 0;
        $statuses             = [];
        $payment_types        = [
            'new'     => [ 'count' => 0, 'total' => 0.0, 'label' => 'New (Stripe retention)' ],
            'renewal' => [ 'count' => 0, 'total' => 0.0, 'label' => 'Renewals (direct payment)' ],
            'coupon'  => [ 'count' => 0, 'total' => 0.0, 'label' => 'With coupon' ],
        ];
        $other_methods        = [];
        $coupons              = [];
        $stripe_capture       = 0.0;
        $stripe_orders        = [];
        $type_statuses        = [ 'new' => [], 'renewal' => [] ];
        $type_totals          = [ 'new' => [], 'renewal' => [] ];
        $products_summary     = [];
        $state_detail         = [];
        $hourly               = [ 'new' => array_fill(0,24,0), 'ren' => array_fill(0,24,0) ];
        $wp_tz                = wp_timezone();

        foreach ( $orders as $order ) {
            $total    = (float) $order->get_total();
            $id       = $order->get_id();
            $status   = $order->get_status();
            $discount = (float) $order->get_discount_total();
            $dt       = $order->get_date_created();

            $statuses[ $status ] = ( $statuses[ $status ] ?? 0 ) + 1;
            $total_discount += $discount;

            if ( $status === 'failed' )    $failed_orders++;
            if ( $status === 'cancelled' ) $cancelled_orders++;
            if ( $status === 'on-hold' )   $on_hold_orders++;

            $local_hour = $dt
                ? (int) ( new DateTime( '@' . $dt->getTimestamp() ) )->setTimezone( $wp_tz )->format('G')
                : 0;

            $is_renewal   = (bool) $order->get_meta('_subscription_renewal');
            $stripe_id    = $order->get_meta('_stripe_intent_id')
                         ?: $order->get_meta('_stripe_charge_id')
                         ?: $order->get_meta('_stripe_source_id');
            $coupon_codes = $order->get_coupon_codes();

            if ( $is_renewal ) {
                $type = 'renewal';
                $hourly['ren'][ $local_hour ]++;
            } elseif ( $stripe_id ) {
                $type = 'new';
                $stripe_capture += $total;
                $stripe_orders[] = [
                    'order_id' => $id,
                    'stripe_id' => $stripe_id,
                    'total'    => $total,
                    'edit_url' => admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $id ),
                ];
                $hourly['new'][ $local_hour ]++;
            } elseif ( ! empty( $coupon_codes ) ) {
                $type = 'coupon';
                $hourly['new'][ $local_hour ]++;
            } else {
                $type = 'other';
            }

            if ( $type === 'other' ) {
                $method_id    = $order->get_payment_method()       ?: 'unknown';
                $method_title = $order->get_payment_method_title() ?: $method_id;
                if ( ! isset( $other_methods[ $method_id ] ) ) {
                    $other_methods[ $method_id ] = [ 'count' => 0, 'total' => 0.0, 'label' => $method_title ];
                }
                $other_methods[ $method_id ]['count']++;
                $other_methods[ $method_id ]['total'] += $total;
            } else {
                $payment_types[ $type ]['count']++;
                $payment_types[ $type ]['total'] += $total;
            }

            if ( isset( $type_statuses[ $type ] ) ) {
                $type_statuses[ $type ][ $status ] = ( $type_statuses[ $type ][ $status ] ?? 0 ) + 1;
                $type_totals[ $type ][ $status ]   = ( $type_totals[ $type ][ $status ] ?? 0.0 ) + $total;
            }

            foreach ( $coupon_codes as $code ) {
                $coupons[ $code ]['count']    = ( $coupons[ $code ]['count'] ?? 0 ) + 1;
                $coupons[ $code ]['discount'] = ( $coupons[ $code ]['discount'] ?? 0.0 ) + $discount;
            }

            if ( $type === 'renewal' && in_array( $status, [ 'completed', 'processing' ] ) ) {
                $total_revenue += $total;
            } elseif ( $type === 'new' && $status === 'completed' ) {
                $total_revenue        += $total;
                $new_revenue_captured += $total;
                $new_completed++;
            }

            foreach ( $order->get_items() as $item ) {
                $name     = $item->get_name();
                $qty      = (int)   $item->get_quantity();
                $subtotal = (float) $item->get_total();

                if ( ! isset( $products_summary[ $name ] ) ) {
                    $products_summary[ $name ] = [ 'name'=>$name,'qty'=>0,'total'=>0.0,'new_qty'=>0,'new_total'=>0.0,'ren_qty'=>0,'ren_total'=>0.0 ];
                }
                $products_summary[ $name ]['qty']   += $qty;
                $products_summary[ $name ]['total'] += $subtotal;
                if ( $type === 'new' ) {
                    $products_summary[ $name ]['new_qty']   += $qty;
                    $products_summary[ $name ]['new_total'] += $subtotal;
                } elseif ( $type === 'renewal' ) {
                    $products_summary[ $name ]['ren_qty']   += $qty;
                    $products_summary[ $name ]['ren_total'] += $subtotal;
                }
            }

            $ship_country = $order->get_shipping_country() ?: $order->get_billing_country();
            $ship_state   = strtoupper( trim( $order->get_shipping_state() ?: $order->get_billing_state() ) );

            if ( $ship_country === 'US' && $ship_state !== '' ) {
                $us_states  = WC()->countries->get_states('US');
                $state_name = $us_states[ $ship_state ] ?? $ship_state;
                if ( ! isset( $state_detail[ $ship_state ] ) ) {
                    $state_detail[ $ship_state ] = [
                        'code'=>$ship_state,'name'=>$state_name,'count'=>0,'total'=>0.0,
                        'new'=>['count'=>0,'total'=>0.0],'renewal'=>['count'=>0,'total'=>0.0],
                        'other'=>['count'=>0,'total'=>0.0],'products'=>[],
                    ];
                }
                $state_detail[ $ship_state ]['count']++;
                $state_detail[ $ship_state ]['total'] += $total;
                $sub_key = in_array($type,['new','renewal']) ? $type : 'other';
                $state_detail[ $ship_state ][ $sub_key ]['count']++;
                $state_detail[ $ship_state ][ $sub_key ]['total'] += $total;
                foreach ( $order->get_items() as $item ) {
                    $pname = $item->get_name();
                    if ( ! isset( $state_detail[ $ship_state ]['products'][ $pname ] ) ) {
                        $state_detail[ $ship_state ]['products'][ $pname ] = [ 'qty'=>0,'total'=>0.0 ];
                    }
                    $state_detail[ $ship_state ]['products'][ $pname ]['qty']   += (int)   $item->get_quantity();
                    $state_detail[ $ship_state ]['products'][ $pname ]['total'] += (float) $item->get_total();
                }
            }
        }

        $start_local = new DateTime( $date . ' 00:00:00', $wp_tz );
        $end_local   = new DateTime( $date . ' 23:59:59', $wp_tz );
        $today_args  = [ 'page'=>'wc-orders','search-filter'=>'all','filter_action'=>'Filter','start_date'=>$start_local,'end_date'=>$end_local ];
        $wc_statuses = wc_get_order_statuses();

        $status_formatted = [];
        foreach ( $statuses as $slug => $count ) {
            $url = str_replace( ' ', '%20', add_query_arg( array_merge( $today_args, [ 'status' => 'wc-'.$slug ] ), admin_url('admin.php') ) );
            $status_formatted[] = [
                'slug'  => $slug,
                'label' => $wc_statuses['wc-'.$slug] ?? ucfirst($slug),
                'count' => $count,
                'url'   => $url,
                'total' => ( $type_totals['new'][$slug] ?? 0 ) + ( $type_totals['renewal'][$slug] ?? 0 ),
            ];
        }

        $type_statuses_fmt = [];
        foreach ( ['new','renewal'] as $t ) {
            $rows = [];
            foreach ( ( $type_statuses[$t] ?? [] ) as $slug => $count ) {
                $url = str_replace( ' ', '%20', add_query_arg( array_merge( $today_args, [ 'status' => 'wc-'.$slug ] ), admin_url('admin.php') ) );
                $rows[] = [ 'slug'=>$slug,'label'=>$wc_statuses['wc-'.$slug]??ucfirst($slug),'count'=>$count,'total'=>$type_totals[$t][$slug]??0.0,'url'=>$url ];
            }
            usort( $rows, fn($a,$b) => $b['count'] - $a['count'] );
            $type_statuses_fmt[$t] = $rows;
        }

        arsort($coupons);
        $coupons_fmt = [];
        foreach ( $coupons as $code => $data ) {
            $coupons_fmt[] = ['code'=>$code,'count'=>$data['count'],'discount'=>$data['discount']];
        }

        uasort( $other_methods,    fn($a,$b) => $b['total'] <=> $a['total'] );
        uasort( $products_summary, fn($a,$b) => $b['total'] <=> $a['total'] );

        $state_detail_fmt = [];
        foreach ( $state_detail as $data ) {
            $prods = [];
            foreach ( $data['products'] as $pname => $pdata ) {
                $prods[] = ['name'=>$pname,'qty'=>$pdata['qty'],'total'=>$pdata['total']];
            }
            usort( $prods, fn($a,$b) => $b['total'] <=> $a['total'] );
            $data['products'] = $prods;
            $state_detail_fmt[] = $data;
        }
        usort( $state_detail_fmt, fn($a,$b) => $b['total'] <=> $a['total'] );

        return [
            'empty'                => false,
            'date'                 => $date,
            'total_orders'         => count($orders),
            'total_revenue'        => $total_revenue,
            'total_discount'       => $total_discount,
            'stripe_capture'       => $stripe_capture,
            'stripe_orders'        => $stripe_orders,
            'new_revenue_captured' => $new_revenue_captured,
            'new_completed'        => $new_completed,
            'failed_orders'        => $failed_orders,
            'cancelled_orders'     => $cancelled_orders,
            'on_hold_orders'       => $on_hold_orders,
            'statuses'             => $status_formatted,
            'payment_types'        => $payment_types,
            'other_methods'        => array_values($other_methods),
            'coupons'              => $coupons_fmt,
            'currency'             => '$',
            'type_statuses'        => $type_statuses_fmt,
            'products_summary'     => array_values($products_summary),
            'state_detail'         => $state_detail_fmt,
            'hourly'               => $hourly,
            'renewal_forecast'     => $this->get_renewal_forecast($date),
        ];
    }

    /**
     * Query Action Scheduler and HPOS orders to build the renewal forecast
     * for the given date, using the correct UTC range derived from WP timezone.
     *
     * Sources (validated against production data):
     *  - Action Scheduler: guaranteed minimum — schedules are the floor for the day.
     *  - Auto renewal orders: shop_order rows with _subscription_renewal, no _stripe_intent_id.
     *  - Manual/early renewals: same but WITH _stripe_intent_id — additional, shown separately.
     *
     * @param string $date Y-m-d in WP local timezone.
     * @return array
     */
    private function get_renewal_forecast( string $date ): array {
        global $wpdb;

        $wp_tz     = wp_timezone();
        $day_start = ( new DateTime( $date . ' 00:00:00', $wp_tz ) )->setTimezone( new DateTimeZone('UTC') )->format('Y-m-d H:i:s');
        $day_end   = ( new DateTime( $date . ' 23:59:59', $wp_tz ) )->setTimezone( new DateTimeZone('UTC') )->format('Y-m-d H:i:s');
        $utc_offset = $this->get_utc_offset_string( $wp_tz );

        $as_rows = $wpdb->get_results( $wpdb->prepare("
            SELECT status, COUNT(*) AS cnt
            FROM {$wpdb->prefix}actionscheduler_actions
            WHERE hook               = 'woocommerce_scheduled_subscription_payment'
              AND scheduled_date_gmt BETWEEN %s AND %s
            GROUP BY status
        ", $day_start, $day_end ), ARRAY_A );

        $as = [ 'complete'=>0, 'pending'=>0, 'failed'=>0, 'in-progress'=>0 ];
        foreach ( $as_rows as $row ) {
            if ( isset( $as[ $row['status'] ] ) ) $as[ $row['status'] ] = (int) $row['cnt'];
        }
        $as_total = array_sum($as);
        $as_pct   = $as_total > 0 ? (int) round( $as['complete'] / $as_total * 100 ) : 0;

        $pending_by_hour = $wpdb->get_results( $wpdb->prepare("
            SELECT DATE_FORMAT( CONVERT_TZ( scheduled_date_gmt, '+00:00', %s ), '%%H:00' ) AS hora_local,
                   COUNT(*) AS cnt
            FROM {$wpdb->prefix}actionscheduler_actions
            WHERE hook               = 'woocommerce_scheduled_subscription_payment'
              AND status             = 'pending'
              AND scheduled_date_gmt BETWEEN %s AND %s
            GROUP BY hora_local
            ORDER BY hora_local
        ", $utc_offset, $day_start, $day_end ), ARRAY_A );

        $auto_rows = $wpdb->get_results( $wpdb->prepare("
            SELECT o.status, COUNT(*) AS cnt, COALESCE(SUM(o.total_amount),0) AS total
            FROM {$wpdb->prefix}wc_orders o
            INNER JOIN ( SELECT order_id FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key = '_subscription_renewal' GROUP BY order_id ) mr ON mr.order_id = o.id
            LEFT  JOIN ( SELECT order_id FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key = '_stripe_intent_id'    GROUP BY order_id ) ms ON ms.order_id = o.id
            WHERE o.type = 'shop_order' AND o.date_created_gmt BETWEEN %s AND %s AND ms.order_id IS NULL
            GROUP BY o.status
        ", $day_start, $day_end ), ARRAY_A );

        $manual_rows = $wpdb->get_results( $wpdb->prepare("
            SELECT o.status, COUNT(*) AS cnt, COALESCE(SUM(o.total_amount),0) AS total
            FROM {$wpdb->prefix}wc_orders o
            INNER JOIN ( SELECT order_id FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key = '_subscription_renewal' GROUP BY order_id ) mr ON mr.order_id = o.id
            INNER JOIN ( SELECT order_id FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key = '_stripe_intent_id'    GROUP BY order_id ) ms ON ms.order_id = o.id
            WHERE o.type = 'shop_order' AND o.date_created_gmt BETWEEN %s AND %s
            GROUP BY o.status
        ", $day_start, $day_end ), ARRAY_A );

        $success_statuses = [
            'wc-completed','completed','wc-processing','processing',
            'wc-send_to_telegra','send_to_telegra','wc-waiting_room','waiting_room',
            'wc-provider_review','provider_review','wc-collect_payment','collect_payment',
            'wc-error_review','error_review','wc-prerequisites','prerequisites','wc-admin_review','admin_review',
        ];
        $failed_statuses = [ 'wc-failed','failed','wc-cancelled','cancelled' ];

        $auto   = $this->classify_order_rows( $auto_rows,   $success_statuses, $failed_statuses );
        $manual = $this->classify_order_rows( $manual_rows, $success_statuses, $failed_statuses );
        $alert  = $this->renewal_alert( $as, $as_pct, $auto );

        return [
            'as_total'        => $as_total,
            'as_complete'     => $as['complete'],
            'as_pending'      => $as['pending'],
            'as_failed'       => $as['failed'],
            'as_in_progress'  => $as['in-progress'],
            'as_pct'          => $as_pct,
            'pending_by_hour' => array_values($pending_by_hour),
            'auto_total'      => $auto['total'],
            'auto_success'    => $auto['success'],
            'auto_failed'     => $auto['failed'],
            'auto_revenue'    => $auto['revenue'],
            'auto_by_status'  => $auto['by_status'],
            'manual_total'    => $manual['total'],
            'manual_success'  => $manual['success'],
            'manual_revenue'  => $manual['revenue'],
            'manual_by_status'=> $manual['by_status'],
            'alert_level'     => $alert['level'],
            'alert_msg'       => $alert['msg'],
        ];
    }

    /**
     * Classify order rows into success / failed / other groups
     * and compute total revenue for successful statuses.
     *
     * @param array  $rows     Raw rows from wpdb (status, cnt, total).
     * @param array  $success  List of status slugs considered successful.
     * @param array  $failed   List of status slugs considered failed.
     * @return array
     */
    private function classify_order_rows( array $rows, array $success, array $failed ): array {
        $out = [ 'total'=>0,'success'=>0,'failed'=>0,'other'=>0,'revenue'=>0.0,'by_status'=>[] ];
        $wc_statuses = wc_get_order_statuses();
        foreach ( $rows as $row ) {
            $cnt  = (int)   $row['cnt'];
            $rev  = (float) $row['total'];
            $slug = $row['status'];
            $out['total']       += $cnt;
            $out['by_status'][]  = [
                'slug'  => $slug,
                'label' => $wc_statuses[ 'wc-'.ltrim($slug,'wc-') ] ?? ucfirst($slug),
                'count' => $cnt,
                'total' => round($rev,2),
            ];
            if ( in_array($slug,$success,true) )      { $out['success'] += $cnt; $out['revenue'] += $rev; }
            elseif ( in_array($slug,$failed,true) )    { $out['failed']  += $cnt; }
            else                                       { $out['other']   += $cnt; }
        }
        $out['revenue'] = round($out['revenue'],2);
        return $out;
    }

    /**
     * Determine alert level and message based on AS state,
     * processed percentage and auto renewal order failures.
     *
     * @param array $as    AS counts keyed by status.
     * @param int   $pct   Percentage of AS actions completed.
     * @param array $auto  Classified auto renewal order summary.
     * @return array { level: 'ok'|'warn'|'bad', msg: string }
     */
    private function renewal_alert( array $as, int $pct, array $auto ): array {
        $hour = (int) ( new DateTime('now', wp_timezone()) )->format('G');

        if ( $as['failed'] > 10 ) {
            return [ 'level'=>'bad', 'msg'=>$as['failed'].' AS actions failed — check logs urgently.' ];
        }
        if ( $auto['total'] > 0 && round($auto['failed']/$auto['total']*100) > 20 ) {
            $fail_pct = round($auto['failed']/$auto['total']*100);
            return [ 'level'=>'bad', 'msg'=>$auto['failed'].' failed/cancelled orders ('.$fail_pct.'%) — high rate, review.' ];
        }
        if ( $hour >= 15 && $as['pending'] > 50 ) {
            return [ 'level'=>'warn', 'msg'=>$as['pending'].' renewals still pending after 3pm — verify Action Scheduler is running.' ];
        }
        if ( $pct >= 80 ) {
            return [ 'level'=>'ok', 'msg'=>$pct.'% of today\'s renewals already processed by Action Scheduler.' ];
        }
        return [ 'level'=>'warn', 'msg'=>$as['pending'].' renewals pending — will be processed throughout the day.' ];
    }

    /**
     * Return the current UTC offset of the given timezone as a signed string
     * suitable for MySQL CONVERT_TZ(), e.g. '-05:00'.
     * Handles DST automatically by using the current moment.
     *
     * @param DateTimeZone $tz
     * @return string
     */
    private function get_utc_offset_string( DateTimeZone $tz ): string {
        $offset  = $tz->getOffset( new DateTime('now',$tz) );
        $sign    = $offset >= 0 ? '+' : '-';
        $abs     = abs($offset);
        $hours   = str_pad( (int) floor($abs/3600), 2, '0', STR_PAD_LEFT );
        $minutes = str_pad( (int) (($abs%3600)/60), 2, '0', STR_PAD_LEFT );
        return $sign.$hours.':'.$minutes;
    }
}

new AH_Daily_Summary();