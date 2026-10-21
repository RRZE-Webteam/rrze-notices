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

    sendDismissRequest(match[1]);

    // Move focus after WordPress finishes the fade-out (~300ms).
    if (focusTarget) {
        setTimeout(function () {
            focusTarget.focus();
        }, 350);
    }
});

/**
 * Handle modal notice acknowledgement.
 *
 * The "Acknowledged" button dismisses the notice, removes the
 * backdrop, releases the focus trap and restores page scrolling.
 */
document.addEventListener('click', function (event) {
    const button = event.target.closest('.rrze-notices-acknowledge');
    if (!button) {
        return;
    }

    const notice = button.closest('.rrze-notices.notice-modal');
    if (!notice) {
        return;
    }

    const match = notice.id.match(/^rrze-notices-(.+)$/);
    if (!match) {
        return;
    }

    sendDismissRequest(match[1]);

    const backdrop = document.querySelector('.rrze-notices-backdrop');
    if (backdrop) {
        backdrop.remove();
    }

    notice.remove();
    document.body.classList.remove('rrze-notices-modal-open');
    document.removeEventListener('keydown', trapFocus, true);

    const focusTarget = document.getElementById('wpbody-content');
    if (focusTarget) {
        focusTarget.focus();
    }
});

/**
 * Send the AJAX dismissal request.
 *
 * @param {string} noticeId The notice identifier.
 */
function sendDismissRequest(noticeId) {
    const body = new URLSearchParams({
        action: 'rrze_notices_dismiss',
        id: noticeId,
        nonce: rrzeNoticesDismiss.nonce,
    });

    fetch(rrzeNoticesDismiss.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
    });
}

/**
 * Initialize modal notices on page load.
 *
 * Adds the body scroll lock, hides the WordPress dismiss button
 * (modal notices use the "Acknowledged" button instead), and
 * activates the keyboard focus trap.
 */
function initModalNotices() {
    const modal = document.querySelector('.rrze-notices.notice-modal');
    if (!modal) {
        return;
    }

    document.body.classList.add('rrze-notices-modal-open');

    document.addEventListener('keydown', trapFocus, true);

    // Set initial focus on the acknowledge button.
    const ackButton = modal.querySelector('.rrze-notices-acknowledge');
    if (ackButton) {
        ackButton.focus();
    }
}

/**
 * Trap keyboard focus inside the modal notice.
 *
 * @param {KeyboardEvent} event Keydown event.
 */
function trapFocus(event) {
    if (event.key !== 'Tab') {
        return;
    }

    const modal = document.querySelector('.rrze-notices.notice-modal');
    if (!modal) {
        return;
    }

    const focusable = modal.querySelectorAll(
        'a[href]:not([disabled]), button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])');
    const elements = Array.from(focusable).filter(function (el) {
        return el.offsetHeight > 0;
    });

    if (elements.length === 0) {
        return;
    }

    const first = elements[0];
    const last = elements[elements.length - 1];

    if (event.shiftKey) {
        if (document.activeElement === first) {
            event.preventDefault();
            last.focus();
        }
    } else {
        if (document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }
}

// Run when the DOM is ready.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initModalNotices);
} else {
    initModalNotices();
}
