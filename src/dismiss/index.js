/**
 * Handle notice dismissal via delegated click listener.
 *
 * WordPress adds a dismiss button (.notice-dismiss) to every notice
 * with the class is-dismissible. This listener intercepts clicks on
 * that button, sends an AJAX request to persist the dismissal, and
 * removes the backdrop when the last error notice is gone.
 */
document.addEventListener('click', function (event) {
    const dismissButton = event.target.closest('.notice-dismiss');
    if (!dismissButton) {
        return;
    }

    const notice = dismissButton.closest('.rrze-notices.is-dismissible');
    if (!notice) {
        return;
    }

    const match = notice.id.match(/^rrze-notices-(.+)$/);
    if (!match) {
        return;
    }

    // Find next focus target before the notice disappears.
    const allNotices = document.querySelectorAll('.rrze-notices.is-dismissible');
    const noticeList = Array.from(allNotices);
    const currentIndex = noticeList.indexOf(notice);
    const nextNotice = noticeList[currentIndex + 1] || noticeList[currentIndex - 1];
    const focusTarget = nextNotice || document.getElementById('wpbody-content');

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

    // Move focus after WordPress finishes the fade-out (~300ms).
    if (focusTarget) {
        setTimeout(function () {
            focusTarget.focus();
        }, 350);
    }
});