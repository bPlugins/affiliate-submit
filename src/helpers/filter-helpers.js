/**
 * Filter Helper Functions
 */

const $ = window.jQuery;

export function applyFilters() {
    const storeFilter = $('#bpas-filter-store').val();
    const typeFilter = $('#bpas-filter-type').val();

    $('#bpas-plugins-tbody tr').each(function () {
        const $row = $(this);
        const storeId = $row.data('store-id');
        const type = $row.data('type');
        let show = true;

        if (storeFilter && storeId != storeFilter) {
            show = false;
        }

        if (typeFilter) {
            if (typeFilter === 'has_term_id') {
                const termsId = $row.find('.bpas-terms-input').val();
                if (!termsId) show = false;
            } else if (typeFilter === 'no_term_id') {
                const termsId = $row.find('.bpas-terms-input').val();
                if (termsId) show = false;
            } else if (type != typeFilter) {
                show = false;
            }
        }

        $row.toggle(show);
    });
}
