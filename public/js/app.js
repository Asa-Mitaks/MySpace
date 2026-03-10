// Myspace Toast Notification System
(function() {
    'use strict';

    // Skip on login/register pages (no session)
    if (!document.querySelector('.navbar')) return;

    // Create toast container
    const container = document.createElement('div');
    container.className = 'toast-container';
    container.id = 'toastContainer';
    document.body.appendChild(container);

    // Track shown notification IDs to avoid duplicates
    let shownIds = new Set();
    let pollInterval = null;
    const POLL_DELAY = 8000; // Check every 8 seconds

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function getTimeAgo(dateStr) {
        const now = new Date();
        const date = new Date(dateStr);
        const seconds = Math.floor((now - date) / 1000);
        if (seconds < 60) return 'Just now';
        const minutes = Math.floor(seconds / 60);
        if (minutes < 60) return minutes + 'm ago';
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return hours + 'h ago';
        return Math.floor(hours / 24) + 'd ago';
    }

    function showToast(notification) {
        if (shownIds.has(notification.id)) return;
        shownIds.add(notification.id);

        const toast = document.createElement('div');
        toast.className = 'toast-notification';
        toast.style.position = 'relative';
        toast.style.overflow = 'hidden';

        const avatarContent = notification.sender_avatar
            ? '<img src="' + escapeHtml(notification.sender_avatar) + '" alt="">'
            : escapeHtml(notification.sender_name.charAt(0).toUpperCase());

        toast.innerHTML =
            '<div class="toast-avatar">' + avatarContent + '</div>' +
            '<div class="toast-body">' +
                '<div class="toast-title">' +
                    '<span class="toast-type-icon">💬</span> ' +
                    escapeHtml(notification.sender_name) +
                '</div>' +
                '<div class="toast-message">' + escapeHtml(notification.preview) + '</div>' +
                '<div class="toast-time">' + getTimeAgo(notification.created_at) + '</div>' +
            '</div>' +
            '<button class="toast-close" aria-label="Close">&times;</button>' +
            '<div class="toast-progress"></div>';

        // Click toast to go to chat with that user
        toast.addEventListener('click', function(e) {
            if (e.target.classList.contains('toast-close')) return;
            window.location.href = 'chat.php?with=' + notification.sender_id;
        });

        // Close button
        toast.querySelector('.toast-close').addEventListener('click', function(e) {
            e.stopPropagation();
            dismissToast(toast);
        });

        container.appendChild(toast);

        // Auto-dismiss after 4 seconds
        setTimeout(function() {
            dismissToast(toast);
        }, 4000);
    }

    function dismissToast(toast) {
        if (toast.classList.contains('toast-exit')) return;
        toast.classList.add('toast-exit');
        setTimeout(function() {
            if (toast.parentNode) toast.parentNode.removeChild(toast);
        }, 400);
    }

    function checkNotifications() {
        fetch('api/notifications.php?action=check', { credentials: 'same-origin' })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.notifications && data.notifications.length > 0) {
                    // Show newest first (reversed since API returns DESC)
                    var toShow = data.notifications.slice().reverse();
                    toShow.forEach(function(n) {
                        showToast(n);
                    });
                }
            })
            .catch(function() {
                // Silently fail - don't break the page
            });
    }

    function markAsRead() {
        fetch('api/notifications.php?action=mark_read', {
            method: 'GET',
            credentials: 'same-origin'
        }).catch(function() {});
    }

    // Don't poll on chat pages (they handle their own messages)
    var isOnChatPage = window.location.pathname.indexOf('chat.php') !== -1
        || window.location.pathname.indexOf('chat-realtime.php') !== -1;

    if (!isOnChatPage) {
        // Initial check after a short delay
        setTimeout(checkNotifications, 2000);
        // Poll periodically
        pollInterval = setInterval(checkNotifications, POLL_DELAY);
    }

    // Mark notifications as read when user navigates to chat
    if (isOnChatPage) {
        markAsRead();
    }

    // Expose for manual triggering if needed
    window.MySpaceNotifications = {
        check: checkNotifications,
        markRead: markAsRead,
        showToast: showToast
    };
})();