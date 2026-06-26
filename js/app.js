// js/app.js
(function () {
    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function getCsrfToken() {
        var el = document.querySelector('input[name="csrf_token"]');
        return el ? el.value : '';
    }

    function submitForm(url, formData, callback) {
        fetch(url, { method: 'POST', body: formData })
            .then(function (r) { return r.json(); })
            .then(callback)
            .catch(function () { alert('Request failed'); });
    }

    // Add Bookmark
    var addForm = document.getElementById('addBookmarkForm');
    if (addForm) {
        addForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(this);
            fd.append('action', 'create');
            submitForm('/bookmarks/api/bookmarks.php', fd, function (data) {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Failed to add bookmark');
                }
            });
        });
    }

    // Auto-fetch title from URL
    var addUrlInput = document.querySelector('#addBookmarkForm input[name="url"]');
    if (addUrlInput) {
        addUrlInput.addEventListener('blur', function () {
            var titleInput = document.querySelector('#addBookmarkForm input[name="title"]');
            if (this.value && !titleInput.value) {
                try {
                    var url = new URL(this.value);
                    titleInput.value = url.hostname;
                    titleInput.focus();
                } catch (e) {}
            }
        });
    }

    // Edit Bookmark - open modal
    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('edit-bookmark')) {
            e.preventDefault();
            var id = e.target.dataset.id;
            var fd = new FormData();
            fd.append('action', 'get');
            fd.append('id', id);
            fd.append('csrf_token', getCsrfToken());
            submitForm('/bookmarks/api/bookmarks.php', fd, function (data) {
                if (data.success && data.bookmark) {
                    document.getElementById('edit_id').value = data.bookmark.id;
                    document.getElementById('edit_url').value = data.bookmark.url;
                    document.getElementById('edit_title').value = data.bookmark.title;
                    document.getElementById('edit_description').value = data.bookmark.description || '';
                    document.getElementById('edit_folder_id').value = data.bookmark.folder_id || '';
                    document.getElementById('edit_tags').value = data.bookmark.tags || '';
                    document.getElementById('edit_visibility').value = data.bookmark.visibility;
                    new bootstrap.Modal(document.getElementById('editBookmarkModal')).show();
                }
            });
        }
    });

    // Edit Bookmark - submit
    var editForm = document.getElementById('editBookmarkForm');
    if (editForm) {
        editForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(this);
            fd.append('action', 'update');
            submitForm('/bookmarks/api/bookmarks.php', fd, function (data) {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('editBookmarkModal')).hide();
                    location.reload();
                } else {
                    alert(data.error || 'Error updating bookmark');
                }
            });
        });
    }

    // Delete Bookmark
    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('delete-bookmark')) {
            e.preventDefault();
            if (!confirm('Delete this bookmark?')) return;
            var fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', e.target.dataset.id);
            fd.append('csrf_token', getCsrfToken());
            submitForm('/bookmarks/api/bookmarks.php', fd, function (data) {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Error deleting bookmark');
                }
            });
        }
    });

    // Add Folder
    var folderForm = document.getElementById('addFolderForm');
    if (folderForm) {
        folderForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(this);
            fd.append('action', 'create');
            submitForm('/bookmarks/api/folders.php', fd, function (data) {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Error creating folder');
                }
            });
        });
    }

    // Delete Folder
    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('delete-folder')) {
            e.preventDefault();
            if (!confirm('Delete folder "' + e.target.dataset.name + '"? Bookmarks will be unassigned.')) return;
            var fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', e.target.dataset.id);
            fd.append('csrf_token', getCsrfToken());
            submitForm('/bookmarks/api/folders.php', fd, function (data) {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Error deleting folder');
                }
            });
        }
    });

    // Share Bookmark - open modal
    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('share-bookmark')) {
            e.preventDefault();
            var bookmarkId = e.target.dataset.id;
            document.getElementById('share_bookmark_id').value = bookmarkId;
            document.getElementById('share_username').value = '';
            document.getElementById('share_suggestions').innerHTML = '';
            loadShares(bookmarkId);
            new bootstrap.Modal(document.getElementById('shareModal')).show();
        }
    });

    // Share Bookmark - submit
    var shareForm = document.getElementById('shareForm');
    if (shareForm) {
        shareForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(this);
            fd.append('action', 'share');
            submitForm('/bookmarks/api/share.php', fd, function (data) {
                if (data.success) {
                    document.getElementById('share_username').value = '';
                    document.getElementById('share_suggestions').innerHTML = '';
                    loadShares(document.getElementById('share_bookmark_id').value);
                } else {
                    alert(data.error || 'Error sharing bookmark');
                }
            });
        });
    }

    // Username search suggestions
    var searchInput = document.getElementById('share_username');
    if (searchInput) {
        var searchTimeout;
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            var query = this.value.trim();
            if (query.length < 2) {
                document.getElementById('share_suggestions').innerHTML = '';
                return;
            }
            searchTimeout = setTimeout(function () {
                var fd = new FormData();
                fd.append('action', 'search_users');
                fd.append('query', query);
                fd.append('csrf_token', getCsrfToken());
                submitForm('/bookmarks/api/share.php', fd, function (data) {
                    if (data.success) {
                        var html = '<div class="list-group">';
                        data.users.forEach(function (u) {
                            html += '<a href="#" class="list-group-item list-group-item-action select-user" data-username="' + escapeHtml(u.username) + '">' + escapeHtml(u.username) + '</a>';
                        });
                        html += '</div>';
                        document.getElementById('share_suggestions').innerHTML = data.users.length ? html : '<small class="text-muted">No users found</small>';
                    }
                });
            }, 300);
        });

        document.addEventListener('click', function (e) {
            if (e.target.classList.contains('select-user')) {
                e.preventDefault();
                document.getElementById('share_username').value = e.target.dataset.username;
                document.getElementById('share_suggestions').innerHTML = '';
            }
        });
    }

    // Unshare
    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('unshare-btn')) {
            e.preventDefault();
            if (!confirm('Stop sharing with this user?')) return;
            var fd = new FormData();
            fd.append('action', 'unshare');
            fd.append('bookmark_id', e.target.dataset.bookmarkId);
            fd.append('user_id', e.target.dataset.userId);
            fd.append('csrf_token', getCsrfToken());
            submitForm('/bookmarks/api/share.php', fd, function (data) {
                if (data.success) {
                    loadShares(e.target.dataset.bookmarkId);
                }
            });
        }
    });

    // Load shares for a bookmark
    function loadShares(bookmarkId) {
        var fd = new FormData();
        fd.append('action', 'get_shares');
        fd.append('bookmark_id', bookmarkId);
        fd.append('csrf_token', getCsrfToken());
        submitForm('/bookmarks/api/share.php', fd, function (data) {
            if (data.success) {
                var html = '<h6 class="mt-3">Shared with:</h6>';
                if (data.shares.length === 0) {
                    html += '<p class="text-muted small">Not shared with anyone yet.</p>';
                } else {
                    data.shares.forEach(function (s) {
                        html += '<div class="d-flex justify-content-between align-items-center mb-1">';
                        html += '<span><i class="bi bi-person"></i> ' + escapeHtml(s.username) + '</span>';
                        html += '<a href="#" class="btn btn-sm btn-outline-danger unshare-btn" data-bookmark-id="' + s.bookmark_id + '" data-user-id="' + s.shared_with_user_id + '">Remove</a>';
                        html += '</div>';
                    });
                }
                document.getElementById('share_existing').innerHTML = html;
            }
        });
    }
})();
