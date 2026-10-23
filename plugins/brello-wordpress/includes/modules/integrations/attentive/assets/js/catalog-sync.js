(function ($) {
    'use strict';

    var STATUS_COLORS = {
        validated: '#00a32a',
        failed: '#d63638',
        initialized: '#dba617',
        validating: '#dba617',
        uploading: '#dba617',
        processing: '#dba617'
    };

    function esc(text) {
        return $('<div>').text(text == null ? '' : text).html();
    }

    function formatErrors(errors) {
        if (!errors || !errors.length) {
            return '<p style="color:#00a32a;margin-top:10px;">No validation errors.</p>';
        }

        var rows = errors.map(function (e) {
            return '<tr>' +
                '<td>' + esc(e.lineNum) + '</td>' +
                '<td>' + esc(e.errorType) + '</td>' +
                '<td>' + esc(e.errorMessage) + '</td>' +
                '</tr>';
        }).join('');

        return '<table class="widefat striped" style="margin-top:10px;">' +
            '<thead><tr><th style="width:70px;">Line</th><th style="width:220px;">Error</th><th>Message</th></tr></thead>' +
            '<tbody>' + rows + '</tbody></table>';
    }

    function renderResult($target, data) {
        var color = STATUS_COLORS[data.status] || '#6b7280';

        var html = '<div style="margin-top:12px;padding:12px;border:1px solid #e5e7eb;background:#fff;max-width:900px;">' +
            '<p><strong>Status:</strong> <span style="color:' + color + ';font-weight:600;">' + esc(data.status || 'unknown') + '</span>' +
            (data.validateOnly ? ' <em>(validation only — nothing sent live)</em>' : ' <strong style="color:#d63638;">(sent live)</strong>') +
            '</p>' +
            '<p><strong>Products received:</strong> ' + esc(data.productsReceived || 0) +
            ' &nbsp; <strong>Processed:</strong> ' + esc(data.productsProcessed || 0) + '</p>' +
            formatErrors(data.errors) +
            '</div>';

        $target.html(html);
    }

    function renderError($target, message) {
        $target.html(
            '<p style="color:#d63638;margin-top:12px;"><strong>Error:</strong> ' + esc(message) + '</p>'
        );
    }

    function runSync($button, $target, validateOnly) {
        var originalText = $button.text();
        $button.prop('disabled', true).text('Processing…');
        $target.html('<p style="margin-top:12px;">Uploading and validating with Attentive — this can take a few seconds…</p>');

        $.post(bhAttentiveCatalogSync.ajaxurl, {
            action: 'bh_attentive_catalog_sync',
            nonce: bhAttentiveCatalogSync.nonce,
            validate_only: validateOnly ? '1' : ''
        }).done(function (response) {
            if (response && response.success) {
                renderResult($target, response.data);
            } else {
                renderError($target, (response && response.data && response.data.message) || 'Unknown error.');
            }
        }).fail(function () {
            renderError($target, 'Request failed. Check your connection and try again.');
        }).always(function () {
            $button.prop('disabled', false).text(originalText);
        });
    }

    $(function () {
        var $result = $('#bh-catalog-sync-result');

        $('#bh-catalog-validate').on('click', function (e) {
            e.preventDefault();
            runSync($(this), $result, true);
        });

        $('#bh-catalog-send').on('click', function (e) {
            e.preventDefault();
            if (!window.confirm('This sends the catalog live to Attentive, replacing what customers currently see. Continue?')) {
                return;
            }
            runSync($(this), $result, false);
        });
    });
})(jQuery);
