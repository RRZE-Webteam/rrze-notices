/**
 * Remove the backdrop when no modal error notices remain visible.
 */
function maybeRemoveBackdrop() {
    if (document.querySelector('.rrze-notices-modal')) {
        return;
    }
    const backdrop = document.querySelector('.rrze-notices-backdrop');
    if (backdrop) {
        backdrop.remove();
    }
}

/**
 * Handle notice dismissal via delegated click listener.
 *
 * WordPress adds a dismiss button (.notice-dismiss) to every notice
 * with the class is-dismissible. This listener intercepts clicks on
 * that button, sends an AJAX request to persist the dismissal, and
 * removes the backdrop when the last error notice is gone.
 */
document.addEventListener('click', function (event) {
    const notice = event.target.closest('.rrze-notices.is-dismissible');
    if (!notice || event.target.closest('a')) {
        return;
    }

    const match = notice.id.match(/^rrze-notices-(.+)$/);
    if (!match) {
        return;
    }

    const body = new URLSearchParams({
        action: 'rrze_notices_dismiss',
        id: match[1],
        nonce: rrzeNoticesDismiss.nonce,
    });

    fetch(rrzeNoticesDismiss.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
    });

    // WordPress removes the notice element after a fadeTo(100ms) +
    // slideUp(100ms) animation. Wait for that to finish, then check
    // if the backdrop can go.
    setTimeout(maybeRemoveBackdrop, 350);
});

/**
 * On page load: set focus and ARIA attributes on the first modal
 * error notice so keyboard and screen-reader users can reach it.
 */
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.querySelector('.rrze-notices-modal');
    if (!modal) {
        return;
    }

    modal.setAttribute('role', 'alertdialog');
    modal.setAttribute('aria-modal', 'true');

    // Use the notice title as the accessible label, if present.
    const title = modal.querySelector('.notice-title');
    if (title) {
        title.id = title.id || 'rrze-notices-modal-title';
        modal.setAttribute('aria-labelledby', title.id);
    }

    // tabindex allows a non-interactive element to receive focus.
    modal.setAttribute('tabindex', '-1');
    modal.focus();
});