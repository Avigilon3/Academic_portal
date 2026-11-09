(function () {
    'use strict';

    var feeds = Array.from(document.querySelectorAll('[data-announcement-feed]'));
    if (!feeds.length) return;

    function formatTime(value) {
        if (!value) return '';
        var date = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return String(value);
        return new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }).format(date);
    }

    function commentMeta(comment) {
        return {
            id: parseInt(comment.id, 10) || 0,
            parentId: comment.parent_comment_id ? parseInt(comment.parent_comment_id, 10) : 0,
            author: comment.author_name || 'Portal user',
            text: comment.comment_text || '',
            time: formatTime(comment.created_at)
        };
    }

    function buildCommentElement(comment) {
        var meta = commentMeta(comment);
        var item = document.createElement('article');
        item.className = 'portal-announcement__comment';
        item.dataset.commentId = String(meta.id);

        var author = document.createElement('strong');
        author.textContent = meta.author;
        item.appendChild(author);

        var body = document.createElement('p');
        body.textContent = meta.text;
        item.appendChild(body);

        if (meta.time) {
            var time = document.createElement('time');
            time.textContent = meta.time;
            item.appendChild(time);
        }

        var actions = document.createElement('div');
        actions.className = 'portal-announcement__comment-actions';
        var replyButton = document.createElement('button');
        replyButton.type = 'button';
        replyButton.className = 'portal-announcement__reply-toggle';
        replyButton.dataset.replyToggle = String(meta.id);
        replyButton.textContent = 'Reply';
        actions.appendChild(replyButton);
        item.appendChild(actions);

        var replies = document.createElement('div');
        replies.className = 'portal-announcement__replies';
        replies.dataset.repliesFor = String(meta.id);
        item.appendChild(replies);

        var form = document.createElement('form');
        form.className = 'portal-announcement__comment-form portal-announcement__reply-form';
        form.dataset.commentForm = '';
        form.dataset.parentId = String(meta.id);
        form.hidden = true;

        var textarea = document.createElement('textarea');
        textarea.name = 'comment';
        textarea.rows = 1;
        textarea.maxLength = 2000;
        textarea.required = true;
        textarea.placeholder = 'Write a reply...';
        form.appendChild(textarea);

        var submit = document.createElement('button');
        submit.type = 'submit';
        submit.setAttribute('aria-label', 'Post reply');
        submit.title = 'Post reply';
        var icon = document.createElement('span');
        icon.className = 'material-symbols-rounded';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = 'send';
        submit.appendChild(icon);
        form.appendChild(submit);

        item.appendChild(form);
        return item;
    }

    function renderComments(list, comments) {
        var groups = {};
        var order = [];
        (comments || []).forEach(function (comment) {
            var meta = commentMeta(comment);
            var parentId = meta.parentId || 0;
            if (!groups[parentId]) {
                groups[parentId] = [];
                order.push(parentId);
            }
            groups[parentId].push(comment);
        });

        list.replaceChildren();
        var topLevel = groups[0] || [];
        topLevel.forEach(function (comment) {
            var item = buildCommentElement(comment);
            var repliesContainer = item.querySelector('[data-replies-for]');
            var replies = groups[commentMeta(comment).id] || [];
            replies.forEach(function (reply) {
                repliesContainer.appendChild(buildCommentElement(reply));
            });
            list.appendChild(item);
        });
    }

    async function sendRequest(feed, card, action, extra) {
        var response = await fetch(feed.dataset.api, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(Object.assign({
                action: action,
                announcement_id: card.dataset.announcementId,
                csrf_token: feed.dataset.csrf
            }, extra || {}))
        });

        var result;
        try {
            result = await response.json();
        } catch (error) {
            throw new Error('The server returned an unreadable response.');
        }
        if (!response.ok || !result.ok) {
            throw new Error(result.message || 'Unable to update this announcement.');
        }
        return result;
    }

    function showError(card, message) {
        var error = card.querySelector('[data-announcement-error]');
        if (!error) {
            error = document.createElement('p');
            error.className = 'portal-announcement__error';
            error.setAttribute('role', 'alert');
            error.setAttribute('data-announcement-error', '');
            var actions = card.querySelector('.portal-announcement__actions');
            if (actions) actions.insertAdjacentElement('afterend', error);
            else card.appendChild(error);
        }
        error.textContent = message;
    }

    function clearError(card) {
        var error = card.querySelector('[data-announcement-error]');
        if (error) error.remove();
    }

    feeds.forEach(function (feed) {
        feed.addEventListener('click', async function (event) {
            var card = event.target.closest('[data-announcement-card]');
            if (!card || !feed.contains(card)) return;

            var reactionButton = event.target.closest('[data-reaction-toggle]');
            if (reactionButton) {
                if (reactionButton.disabled) return;
                reactionButton.disabled = true;
                clearError(card);
                try {
                    var reaction = await sendRequest(feed, card, 'toggle_reaction');
                    reactionButton.setAttribute('aria-pressed', reaction.reacted ? 'true' : 'false');
                    reactionButton.classList.toggle('is-active', reaction.reacted);
                    var label = reactionButton.querySelector('[data-reaction-label]');
                    var count = reactionButton.querySelector('[data-reaction-count]');
                    if (label) label.textContent = reaction.reacted ? 'Liked' : 'Like';
                    if (count) count.textContent = String(reaction.reaction_count);
                } catch (error) {
                    showError(card, error.message);
                } finally {
                    reactionButton.disabled = false;
                }
                return;
            }

            var replyToggle = event.target.closest('[data-reply-toggle]');
            if (replyToggle) {
                var commentId = replyToggle.dataset.replyToggle;
                var comment = card.querySelector('[data-comment-id="' + commentId + '"]');
                var form = comment ? comment.querySelector('.portal-announcement__reply-form') : null;
                if (form) {
                    form.hidden = !form.hidden;
                    if (!form.hidden) {
                        var textarea = form.querySelector('textarea');
                        if (textarea) textarea.focus();
                    }
                }
                return;
            }

            var commentsButton = event.target.closest('[data-comments-toggle]');
            if (commentsButton) {
                var thread = card.querySelector('[data-comment-thread]');
                if (!thread) return;
                var willOpen = thread.hidden;
                thread.hidden = !willOpen;
                commentsButton.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                if (willOpen && card.dataset.commentsLoaded !== 'true') {
                    var list = thread.querySelector('[data-comment-list]');
                    var loadButton = thread.querySelector('[data-load-comments]');
                    if (loadButton) loadButton.disabled = true;
                    try {
                        var comments = await sendRequest(feed, card, 'list_comments');
                        renderComments(list, comments.comments);
                        card.dataset.commentsLoaded = 'true';
                        var count = card.querySelector('[data-comment-count]');
                        if (count) count.textContent = String(comments.comment_count);
                        if (loadButton) loadButton.remove();
                    } catch (error) {
                        showError(card, error.message);
                    } finally {
                        if (loadButton) loadButton.disabled = false;
                    }
                }
                return;
            }

            var loadCommentsButton = event.target.closest('[data-load-comments]');
            if (loadCommentsButton) {
                var commentList = card.querySelector('[data-comment-list]');
                loadCommentsButton.disabled = true;
                clearError(card);
                try {
                    var allComments = await sendRequest(feed, card, 'list_comments');
                    renderComments(commentList, allComments.comments);
                    card.dataset.commentsLoaded = 'true';
                    loadCommentsButton.remove();
                } catch (error) {
                    showError(card, error.message);
                    loadCommentsButton.disabled = false;
                }
            }
        });

        feed.addEventListener('submit', async function (event) {
            var form = event.target.closest('[data-comment-form]');
            if (!form || !feed.contains(form)) return;
            event.preventDefault();

            var card = form.closest('[data-announcement-card]');
            var textarea = form.querySelector('textarea');
            var submit = form.querySelector('button[type="submit"]');
            var comment = textarea ? textarea.value.trim() : '';
            if (!card || !textarea || !comment) return;

            var parentId = form.dataset.parentId ? parseInt(form.dataset.parentId, 10) : 0;
            if (submit) submit.disabled = true;
            clearError(card);
            try {
                var result = await sendRequest(feed, card, 'add_comment', {
                    comment: comment,
                    parent_id: parentId || null
                });
                var thread = card.querySelector('[data-comment-thread]');
                var list = thread.querySelector('[data-comment-list]');
                thread.hidden = false;
                var commentsToggle = card.querySelector('[data-comments-toggle]');
                if (commentsToggle) commentsToggle.setAttribute('aria-expanded', 'true');

                var comments = await sendRequest(feed, card, 'list_comments');
                renderComments(list, comments.comments);
                card.dataset.commentsLoaded = 'true';

                textarea.value = '';
                form.hidden = parentId !== 0;
                var count = card.querySelector('[data-comment-count]');
                if (count) count.textContent = String(comments.comment_count);
                var loadButton = thread.querySelector('[data-load-comments]');
                if (loadButton) loadButton.remove();
            } catch (error) {
                showError(card, error.message);
            } finally {
                if (submit) submit.disabled = false;
            }
        });
    });
})();