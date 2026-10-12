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

});
