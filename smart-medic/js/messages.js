(function () {
    var newMessageButton = document.getElementById('new-message-button');
    var newMessagePanel = document.getElementById('new-message-panel');
    var userSearch = document.getElementById('user-search');
    var availableUsers = document.querySelectorAll('.available-user');
    var noResults = document.getElementById('messages-no-results');

    function setNewMessagePanel(open) {
        if (!newMessageButton || !newMessagePanel) {
            return;
        }

        newMessagePanel.hidden = !open;
        newMessageButton.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open && userSearch) {
            userSearch.focus();
        }
    }

    if (newMessageButton) {
        newMessageButton.addEventListener('click', function () {
            setNewMessagePanel(newMessagePanel.hidden);
        });
    }

    document.querySelectorAll('[data-open-new-message]').forEach(function (button) {
        button.addEventListener('click', function () {
            setNewMessagePanel(true);
        });
    });

    if (userSearch) {
        userSearch.addEventListener('input', function () {
            var searchTerm = userSearch.value.trim().toLowerCase();
            var visibleCount = 0;

            availableUsers.forEach(function (user) {
                var matches = user.dataset.userSearch.indexOf(searchTerm) !== -1;
                user.hidden = !matches;
                if (matches) {
                    visibleCount += 1;
                }
            });

            if (noResults) {
                noResults.hidden = visibleCount !== 0;
            }
        });
    }

    var history = document.getElementById('message-history');

    if (!history) {
        return;
    }

    var userId = history.dataset.userId;
    var currentUserId = Number(history.dataset.currentUserId);
    var fetchUrl = history.dataset.fetchUrl + '?user_id=' + encodeURIComponent(userId);
    var markReadUrl = history.dataset.markReadUrl;
    var pollingTimer = null;
    var isFetching = false;

    function updateUnreadCount(count) {
        var total = document.getElementById('messages-unread-count');
        if (!total) {
            return;
        }

        total.textContent = count > 99 ? '99+' : String(count);
        total.hidden = count < 1;
    }

    function markConversationRead() {
        return fetch(markReadUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                Accept: 'application/json'
            },
            body: 'user_id=' + encodeURIComponent(userId),
            credentials: 'same-origin'
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Unable to update message status.');
            }
            return response.json();
        });
    }

    function formatDate(value) {
        var date = new Date(value.replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) {
            return value;
        }

        return date.toLocaleString([], {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        });
    }

    function renderMessages(messages, shouldScroll) {
        var existingScrollPosition = history.scrollTop;
        var wasNearBottom = history.scrollHeight - history.scrollTop - history.clientHeight < 80;
        history.replaceChildren();

        if (!messages.length) {
            var emptyState = document.createElement('p');
            emptyState.className = 'message-empty-state';
            emptyState.textContent = 'No messages yet. Start a conversation.';
            history.appendChild(emptyState);
        } else {
            messages.forEach(function (message) {
                var bubble = document.createElement('div');
                bubble.className = 'message-bubble ' + (Number(message.sender_id) === currentUserId ? 'is-sent' : 'is-received');
                bubble.dataset.messageId = message.id;

                var text = document.createElement('p');
                text.textContent = message.message;
                bubble.appendChild(text);

                var timestamp = document.createElement('time');
                timestamp.dateTime = message.created_at;
                timestamp.textContent = formatDate(message.created_at);
                bubble.appendChild(timestamp);
                history.appendChild(bubble);
            });
        }

        if (shouldScroll || wasNearBottom) {
            history.scrollTop = history.scrollHeight;
        } else {
            history.scrollTop = existingScrollPosition;
        }
    }

    function fetchMessages(shouldScroll) {
        if (isFetching) {
            return;
        }

        isFetching = true;
        fetch(fetchUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Unable to load messages.');
                }
                return response.json();
            })
            .then(function (data) {
                if (!data.success || !Array.isArray(data.messages)) {
                    throw new Error(data.message || 'Unable to load messages.');
                }
                updateUnreadCount(Number(data.unread_count) || 0);
                renderMessages(data.messages, shouldScroll);
            })
            .catch(function () {
                var errorState = document.createElement('p');
                errorState.className = 'message-empty-state message-error-state';
                errorState.textContent = 'Messages could not be loaded. Please try again.';
                history.replaceChildren(errorState);
            })
            .finally(function () {
                isFetching = false;
            });
    }

    markConversationRead()
        .then(function () {
            fetchMessages(true);
        })
        .catch(function () {
            fetchMessages(false);
        });
    pollingTimer = window.setInterval(function () {
        fetchMessages(false);
    }, 3000);

    window.addEventListener('beforeunload', function () {
        if (pollingTimer !== null) {
            window.clearInterval(pollingTimer);
        }
    });
})();
