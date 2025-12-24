// Import SASS
import './dashboard.scss';

// Import helpers
import { showSavedIndicator, showError } from '../helpers/ui-helpers';
import { applyFilters } from '../helpers/filter-helpers';

(function ($) {
    'use strict';

    const BPASAdmin = {
        init() {
            this.loadPlugins();
            this.bindEvents();
        },

        bindEvents() {
            // Select All checkbox
            $(document).on('click', '#bpas-select-all', function () {
                $('.bpas-plugin-checkbox').prop('checked', this.checked);
            });

            // Save Terms ID
            $(document).on('click', '.bpas-save-terms', function () {
                const $btn = $(this);
                const pluginId = $btn.data('plugin-id');
                const termsId = $(`.bpas-terms-input[data-plugin-id="${pluginId}"]`).val();

                localStorage.setItem(`bpas_terms_${pluginId}`, termsId);
                showSavedIndicator(pluginId);
            });

            // Filter changes
            $('#bpas-filter-store, #bpas-filter-type').on('change', function () {
                applyFilters();
            });

            // Reset filters
            $('#bpas-reset-filters').on('click', function () {
                $('#bpas-filter-store, #bpas-filter-type').val('');
                applyFilters();
            });

            // Submit affiliates
            $('#bpas-submit-btn').on('click', function (e) {
                e.preventDefault();
                BPASAdmin.submitAffiliates();
            });

            // Test Connection
            $('#bpas-test-connection').on('click', function (e) {
                e.preventDefault();
                BPASAdmin.testConnection();
            });

            // Clear Clock Sync
            $('#bpas-clear-sync').on('click', function (e) {
                e.preventDefault();
                BPASAdmin.clearSync();
            });

            // Table Sorting
            $(document).on('click', '.bpas-sortable', function () {
                const $header = $(this);
                const column = $header.data('sort');
                let direction = 'asc';

                if ($header.hasClass('asc')) {
                    direction = 'desc';
                }

                $('.bpas-sortable').removeClass('asc desc');
                $header.addClass(direction);

                BPASAdmin.handleSorting(column, direction);
            });
        },

        handleSorting(column, direction) {
            const $tbody = $('#bpas-plugins-tbody');
            const rows = $tbody.find('tr').get();

            rows.sort((a, b) => {
                let valA, valB;

                if (column === 'id') {
                    valA = parseInt($(a).find('td:nth-child(2)').text(), 10);
                    valB = parseInt($(b).find('td:nth-child(2)').text(), 10);
                } else if (column === 'name') {
                    valA = $(a).find('td:nth-child(3)').text().toLowerCase();
                    valB = $(b).find('td:nth-child(3)').text().toLowerCase();
                }

                if (valA < valB) return direction === 'asc' ? -1 : 1;
                if (valA > valB) return direction === 'asc' ? 1 : -1;
                return 0;
            });

            $.each(rows, (index, row) => {
                $tbody.append(row);
            });
        },

        clearSync() {
            const $btn = $('#bpas-clear-sync');
            const $result = $('#bpas-test-result');

            $btn.prop('disabled', true);

            $.ajax({
                url: bpasData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bpas_clear_sync',
                    nonce: bpasData.nonce
                },
                success(response) {
                    $btn.prop('disabled', false);
                    if (response.success) {
                        $result.html('<div class="bpas-alert success">' + response.data + '</div>');
                    }
                }
            });
        },

        testConnection() {
            const $btn = $('#bpas-test-connection');
            const $spinner = $('#bpas-test-spinner');
            const $result = $('#bpas-test-result');

            $btn.prop('disabled', true);
            $spinner.addClass('is-active');
            $result.html('');

            $.ajax({
                url: bpasData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bpas_test_connection',
                    nonce: bpasData.nonce
                },
                success(response) {
                    $btn.prop('disabled', false);
                    $spinner.removeClass('is-active');
                    console.log(response);

                    if (response.success) {
                        $result.html('<div class="bpas-alert success">' + response.data + '</div>');
                    } else {
                        $result.html('<div class="bpas-alert error">' + response.data + '</div>');
                    }
                },
                error() {
                    $btn.prop('disabled', false);
                    $spinner.removeClass('is-active');
                    $result.html('<div class="bpas-alert error">Server error testing connection</div>');
                }
            });
        },

        loadPlugins() {
            const $loading = $('#bpas-loading');
            const $table = $('#bpas-plugins-table');
            const $filters = $('#bpas-filters');

            $.ajax({
                url: bpasData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bpas_fetch_plugins',
                    nonce: bpasData.nonce
                },
                success(response) {
                    $loading.hide();

                    if (response.success) {
                        BPASAdmin.renderPlugins(response.data);
                        $table.show();
                        $filters.show();
                    } else {
                        showError('Failed to load plugins: ' + response.data);
                    }
                },
                error() {
                    $loading.hide();
                    showError('Server error loading plugins');
                }
            });
        },

        renderPlugins(plugins) {
            const $tbody = $('#bpas-plugins-tbody');
            const stores = {};

            plugins.forEach(plugin => {
                console.log(plugin);

                if (plugin.store_id !== undefined && plugin.store_id !== null) {
                    stores[plugin.store_id] = true;
                }

                const hasAffiliation = plugin.has_affiliation || plugin.is_affiliate_program_enabled;
                const isPremium = plugin.total_purchases > 0 ? 'premium' : 'free';
                const savedTermsId = localStorage.getItem(`bpas_terms_${plugin.id}`) || '';

                const row = `
          <tr data-store-id="${plugin.store_id || ''}" data-type="${isPremium}">
            <td class="bpas-check-column">
              <input type="checkbox" class="bpas-plugin-checkbox" value="${plugin.id}" data-name="${plugin.title}">
            </td>
            <td>${plugin.id}</td>
            <td>${plugin.title}</td>
            <td>
              <span class="bpas-status-badge ${hasAffiliation ? 'enabled' : 'disabled'}">
                ${hasAffiliation ? 'Enabled' : 'Unknown'}
              </span>
            </td>
            <td>
              <div style="display: flex; align-items: center; gap: 8px;">
                <input type="number" class="bpas-terms-input" data-plugin-id="${plugin.id}" 
                       placeholder="Terms ID" value="${savedTermsId}" style="width: 80px;">
                <button type="button" class="bpas-btn bpas-btn-small bpas-save-terms" data-plugin-id="${plugin.id}">
                  Save
                </button>
                <span class="bpas-saved-indicator bpas-saved-${plugin.id}">✓ Saved</span>
              </div>
            </td>
            <td class="bpas-status-${plugin.id}"></td>
          </tr>
        `;

                $tbody.append(row);
            });

            // Populate store filter
            Object.keys(stores).forEach(storeId => {
                $('#bpas-filter-store').append(`<option value="${storeId}">Store ${storeId}</option>`);
            });
        },

        submitAffiliates() {
            const email = $('#bpas-email').val();
            const name = $('#bpas-name').val();
            const domain = $('#bpas-domain').val();
            const paypalEmail = $('#bpas-paypal-email').val();
            const additionalDomains = $('#bpas-additional-domains').val();
            const promotionalMethods = $('.bpas-promotional-method:checked').map(function () {
                return $(this).val();
            }).get().join(',');
            const statsDescription = $('#bpas-stats-description').val();
            const promotionMethodDescription = $('#bpas-promotion-method-description').val();
            const selected = $('.bpas-plugin-checkbox:checked');

            if (!email || !name) {
                showError('Please fill in required fields');
                return;
            }

            if (selected.length === 0) {
                showError('Please select at least one product');
                return;
            }

            const queue = [];
            selected.each(function () {
                queue.push({
                    id: $(this).val(),
                    name: $(this).data('name')
                });
            });

            $('#bpas-submit-btn').prop('disabled', true);
            $('#bpas-log').show().html('<div class="bpas-log-entry">Starting submission...</div>');

            BPASAdmin.processQueue(queue, {
                email,
                name,
                domain,
                paypalEmail,
                additionalDomains,
                promotionalMethods,
                statsDescription,
                promotionMethodDescription
            });
        },

        processQueue(queue, data) {
            if (queue.length === 0) {
                $('#bpas-submit-btn').prop('disabled', false);
                $('#bpas-log').append('<div class="bpas-log-entry success"><strong>All Done!</strong></div>');
                return;
            }

            const item = queue.shift();
            const termsId = $(`.bpas-terms-input[data-plugin-id="${item.id}"]`).val();
            const $statusCell = $(`.bpas-status-${item.id}`);

            $statusCell.html('<span style="color: #FF7A00;">Processing...</span>');
            $('#bpas-log').append(`<div class="bpas-log-entry">Processing ${item.name}...</div>`);

            $.ajax({
                url: bpasData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bpas_add_affiliate',
                    nonce: bpasData.nonce,
                    plugin_id: item.id,
                    terms_id: termsId,
                    email: data.email,
                    name: data.name,
                    domain: data.domain,
                    paypal_email: data.paypalEmail,
                    additional_domains: data.additionalDomains,
                    promotional_methods: data.promotionalMethods,
                    stats_description: data.statsDescription,
                    promotion_method_description: data.promotionMethodDescription
                },
                success(response) {
                    if (response.success) {
                        $statusCell.html('<span style="color: #146EF5;">✓ Success</span>');
                        $('#bpas-log').append(`<div class="bpas-log-entry success">✓ ${item.name}: ${response.data}</div>`);
                    } else {
                        $statusCell.html('<span style="color: #FF7A00;">✗ Failed</span>');
                        $('#bpas-log').append(`<div class="bpas-log-entry error">✗ ${item.name}: ${response.data}</div>`);
                    }
                    BPASAdmin.processQueue(queue, data);
                },
                error() {
                    $statusCell.html('<span style="color: #FF7A00;">✗ Error</span>');
                    $('#bpas-log').append(`<div class="bpas-log-entry error">✗ ${item.name}: Server error</div>`);
                    BPASAdmin.processQueue(queue, data);
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(() => {
        if ($('#bpasAffiliateSubmit').length) {
            BPASAdmin.init();
        }
    });

})(jQuery);
