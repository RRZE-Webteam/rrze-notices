function addExpiredOption(selectSelector) {
    const select = document.querySelector(selectSelector);
    if (!select) {
        return;
    }

    const option = document.createElement('option');
    option.value = rrzeNoticesPostStatus.statusValue;
    option.textContent = rrzeNoticesPostStatus.statusLabel;

    if (rrzeNoticesPostStatus.currentStatus === option.value) {
        option.selected = true;
    }

    select.appendChild(option);
}

// Publish metabox dropdown
addExpiredOption('select#post_status');

// Update status display text if currently expired
if (rrzeNoticesPostStatus.currentStatus ===
    rrzeNoticesPostStatus.statusValue) {
    const display = document.querySelector('.misc-pub-post-status span#post-status-display');
    if (display) {
        display.textContent = rrzeNoticesPostStatus.statusLabel;
    }
}

// Quick Edit dropdown
addExpiredOption('select[name="_status"]');