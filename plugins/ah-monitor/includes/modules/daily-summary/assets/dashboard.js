/* WC Daily Summary Dashboard JS */
(function($) {
    'use strict';

    let allOrders = [];
    let paymentChart = null;

    const fmt = (amount, currency) => {
        return currency + parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    };

    const statusColors = {
        completed:  '#00c896',
        processing: '#6c47ff',
        'on-hold':  '#f59e0b',
        cancelled:  '#ef4444',
        refunded:   '#6b7280',
        failed:     '#dc2626',
        pending:    '#94a3b8',
    };

    const getStatusColor = (slug) => statusColors[slug] || '#94a3b8';

    const typeLabels = {
        new:     { label: 'Nueva', icon: '⚡', cls: 'new' },
        renewal: { label: 'Renovación', icon: '🔄', cls: 'renewal' },
        coupon:  { label: 'Cupón', icon: '🏷️', cls: 'coupon' },
        other:   { label: 'Otra', icon: '•', cls: 'other' },
    };

    function loadSummary() {console.log('loadSummary');
        const date = $('#wcds-date').val();
        if (!date) return;

        $('#wcds-loading').show();
        $('#wcds-content').hide();
        $('#wcds-empty').hide();

        $.post(wcds.ajax_url, {
            action: 'wcds_get_summary',
            nonce:  wcds.nonce,
            date:   date,
        }, function(response) {
            $('#wcds-loading').hide();

            if (!response.success) {
                alert('Error al cargar datos.');
                return;
            }

            const data = response.data;

            if (data.empty) {
                $('#wcds-empty').show();
                return;
            }

            renderKPIs(data);
            renderStatuses(data);
            renderPayments(data);
            renderStripe(data);
            renderCoupons(data);
            renderOrders(data);
            populateStatusFilter(data.statuses);

            $('#wcds-content').show();
        }).fail(function() {
            $('#wcds-loading').hide();
            alert('Error de conexión.');
        });
    }

    function renderKPIs(data) {
        const c = data.currency;
        $('#wcds-kpis').html(`
            <div class="wcds-kpi-card kpi-revenue">
                <div class="wcds-kpi-label">Ingresos del Día</div>
                <div class="wcds-kpi-value">${fmt(data.total_revenue, c)}</div>
                <div class="wcds-kpi-sub">Completadas + En proceso</div>
                <div class="wcds-kpi-icon">💰</div>
            </div>
            <div class="wcds-kpi-card kpi-orders">
                <div class="wcds-kpi-label">Total Órdenes</div>
                <div class="wcds-kpi-value">${data.total_orders}</div>
                <div class="wcds-kpi-sub">Todos los estados</div>
                <div class="wcds-kpi-icon">📋</div>
            </div>
            <div class="wcds-kpi-card kpi-stripe">
                <div class="wcds-kpi-label">Captura Stripe</div>
                <div class="wcds-kpi-value">${fmt(data.stripe_capture, c)}</div>
                <div class="wcds-kpi-sub">${data.stripe_orders.length} órdenes nuevas</div>
                <div class="wcds-kpi-icon">⚡</div>
            </div>
            <div class="wcds-kpi-card kpi-discount">
                <div class="wcds-kpi-label">Descuentos</div>
                <div class="wcds-kpi-value">${fmt(data.total_discount, c)}</div>
                <div class="wcds-kpi-sub">${data.coupons.length} cupones distintos</div>
                <div class="wcds-kpi-icon">🏷️</div>
            </div>
        `);
    }

    function renderStatuses(data) {
        if (!data.statuses.length) {
            $('#wcds-status-list').html('<p style="color:#aab0bc;font-size:13px;">Sin datos</p>');
            return;
        }

        const html = data.statuses.map(s => `
            <div class="wcds-status-item status-${s.slug}">
                <div class="wcds-status-left">
                    <div class="wcds-status-dot" style="background:${getStatusColor(s.slug)}"></div>
                    <div class="wcds-status-label">${s.label}</div>
                </div>
                <div class="wcds-status-count">${s.count}</div>
            </div>
        `).join('');

        $('#wcds-status-list').html(html);
    }

    function renderPayments(data) {
        const c = data.currency;
        const types = data.payment_types;
        const icons = { new: '⚡', renewal: '🔄', coupon: '🏷️', other: '•' };

        const html = Object.entries(types).map(([key, val]) => {
            if (val.count === 0) return '';
            return `
                <div class="wcds-payment-item type-${key}">
                    <div class="wcds-payment-icon">${icons[key]}</div>
                    <div class="wcds-payment-info">
                        <div class="wcds-payment-name">${val.label}</div>
                        <div class="wcds-payment-count">${val.count} orden${val.count !== 1 ? 'es' : ''}</div>
                    </div>
                    <div class="wcds-payment-total">${fmt(val.total, c)}</div>
                </div>
            `;
        }).join('');

        $('#wcds-payment-list').html(html || '<p style="color:#aab0bc;font-size:13px;">Sin datos</p>');

        // Donut chart
        const chartData = Object.entries(types).filter(([,v]) => v.count > 0);
        if (chartData.length > 0) {
            if (paymentChart) paymentChart.destroy();
            const ctx = document.getElementById('wcds-payment-chart').getContext('2d');
            paymentChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: chartData.map(([,v]) => v.label),
                    datasets: [{
                        data: chartData.map(([,v]) => v.total),
                        backgroundColor: ['#6c47ff','#00c896','#f59e0b','#94a3b8'],
                        borderWidth: 2,
                        borderColor: '#fff',
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { font: { size: 11 }, boxWidth: 12, padding: 10 }
                        },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => ` ${c}${parseFloat(ctx.raw).toFixed(2)}`
                            }
                        }
                    },
                    cutout: '65%',
                }
            });
        }
    }

    function renderStripe(data) {
        const c = data.currency;
        const wc_stripe  = data.stripe_capture;
        const diff       = 0; // Real diff would come from Stripe API
        const matchClass = 'match';
        const matchText  = '✓ Pendiente verificación con Stripe API';

        let listHtml = '';
        if (data.stripe_orders.length) {
            listHtml = '<div class="wcds-stripe-list">';
            data.stripe_orders.slice(0, 8).forEach(o => {
                listHtml += `
                    <div class="wcds-stripe-list-item">
                        <a href="${o.edit_url || '#'}" target="_blank">#${o.order_id}</a>
                        <span style="font-family:monospace;font-size:11px;">${o.stripe_id ? o.stripe_id.substring(0,20)+'…' : '-'}</span>
                        <strong>${fmt(o.total, c)}</strong>
                    </div>
                `;
            });
            if (data.stripe_orders.length > 8) {
                listHtml += `<div style="text-align:center;padding:4px;color:#9ca3af;font-size:11px;">+${data.stripe_orders.length - 8} más</div>`;
            }
            listHtml += '</div>';
        }

        $('#wcds-stripe-data').html(`
            <div class="wcds-stripe-compare">
                <div class="wcds-stripe-row">
                    <span class="wcds-stripe-row-label">Total WC (captura Stripe)</span>
                    <span class="wcds-stripe-row-value">${fmt(wc_stripe, c)}</span>
                </div>
                <div class="wcds-stripe-row" style="opacity:0.6">
                    <span class="wcds-stripe-row-label">Total Stripe (requiere API key)</span>
                    <span class="wcds-stripe-row-value" style="color:#94a3b8">—</span>
                </div>
                <div class="wcds-stripe-diff ${matchClass}">
                    <span>${matchText}</span>
                    <span>${data.stripe_orders.length} payments</span>
                </div>
            </div>
            ${listHtml}
        `);
    }

    function renderCoupons(data) {
        if (!data.coupons.length) {
            $('#wcds-coupons-list').html('<p style="color:#aab0bc;font-size:13px;">Sin cupones hoy</p>');
            return;
        }

        const c = data.currency;
        const html = data.coupons.map(coupon => `
            <div class="wcds-coupon-item">
                <span class="wcds-coupon-code">${coupon.code}</span>
                <div class="wcds-coupon-right">
                    <span>${coupon.count}×</span>
                    <span class="wcds-coupon-discount">-${fmt(coupon.discount, c)}</span>
                </div>
            </div>
        `).join('');

        $('#wcds-coupons-list').html(html);
    }

    function renderOrders(data) {
        allOrders = data.orders;
        const c = data.currency;
        renderOrdersTable(allOrders, c);
    }

    function renderOrdersTable(orders, currency) {
        const c = currency || '';
        if (!orders.length) {
            $('#wcds-orders-tbody').html('<tr><td colspan="9" style="text-align:center;color:#aab0bc;padding:30px;">Sin resultados</td></tr>');
            return;
        }

        const rows = orders.map(o => {
            const tInfo = typeLabels[o.type] || typeLabels.other;
            const couponStr = o.coupons.length ? o.coupons.map(cp => `<span class="wcds-coupon-code">${cp}</span>`).join(' ') : '—';
            const stripeStr = o.stripe_id ? `<span class="wcds-stripe-id" title="${o.stripe_id}">${o.stripe_id}</span>` : '—';
            const badgeClass = `badge-${o.status}`;

            return `
                <tr data-status="${o.status}" data-type="${o.type}">
                    <td><a href="${o.edit_url || '#'}" target="_blank" class="wcds-order-link">#${o.id}</a></td>
                    <td style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${o.customer}">${o.customer}</td>
                    <td><span class="wcds-badge ${badgeClass}">${o.status}</span></td>
                    <td><span class="type-badge type-badge-${tInfo.cls}">${tInfo.icon} ${tInfo.label}</span></td>
                    <td>${couponStr}</td>
                    <td style="color:#dc2626;font-weight:600;">${o.discount > 0 ? '-' + fmt(o.discount, c) : '—'}</td>
                    <td style="font-weight:700;">${fmt(o.total, c)}</td>
                    <td>${stripeStr}</td>
                    <td style="color:#9ca3af;">${o.time}</td>
                </tr>
            `;
        }).join('');

        $('#wcds-orders-tbody').html(rows);
    }

    function populateStatusFilter(statuses) {
        let opts = '<option value="">Todos los estados</option>';
        statuses.forEach(s => {
            opts += `<option value="${s.slug}">${s.label} (${s.count})</option>`;
        });
        $('#wcds-filter-status').html(opts);
    }

    function applyFilters() {
        const statusFilter = $('#wcds-filter-status').val();
        const typeFilter   = $('#wcds-filter-type').val();

        let filtered = allOrders;
        if (statusFilter) filtered = filtered.filter(o => o.status === statusFilter);
        if (typeFilter)   filtered = filtered.filter(o => o.type === typeFilter);

        renderOrdersTable(filtered);
    }

    // Events
    $(document).ready(function() {
        loadSummary();

        $('#wcds-refresh').on('click', loadSummary);
        $('#wcds-date').on('change', loadSummary);
        $('#wcds-filter-status, #wcds-filter-type').on('change', applyFilters);
    });

})(jQuery);
