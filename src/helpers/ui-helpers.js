/**
 * UI Helper Functions
 */

const $ = window.jQuery;

export function showSavedIndicator(pluginId) {
    const $indicator = $(`.bpas-saved-${pluginId}`);
    $indicator.addClass('show');
    setTimeout(() => {
        $indicator.removeClass('show');
    }, 2000);
}

export function showError(message) {
    alert(message);
}
