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