<?php
// duplicates.php - Duplicate Bookmark Finder
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$pageTitle = 'Duplicate Finder';
$csrf = generate_csrf_token();
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-clouds-fill"></i> Duplicate Bookmark Finder</h2>
    <a href="/bookmarks/settings.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Settings</a>
</div>

<div class="card shadow mb-4">
    <div class="card-header"><h5 class="mb-0">Duplicate URLs</h5></div>
    <div class="card-body" id="duplicatesContainer">
        <!-- Spinner while loading -->
        <div class="text-center py-5" id="loader">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="text-muted mt-2">Scanning your library for duplicate URLs...</p>
        </div>
    </div>
</div>

<!-- Floating bottom action bar -->
<div class="card shadow fixed-bottom m-3 d-none" id="actionBar" style="z-index: 1030; background: var(--card-bg); border: 1px solid var(--primary-glow) !important; backdrop-filter: blur(16px);">
    <div class="card-body d-flex justify-content-between align-items-center py-2 px-4">
        <div>
            <span class="fw-semibold text-primary" id="selectedCount">0</span> bookmarks selected for deletion.
        </div>
        <div>
            <button class="btn btn-sm btn-outline-secondary me-2" id="btnSelectAll">Select All Duplicates</button>
            <button class="btn btn-sm btn-outline-secondary me-2" id="btnDeselectAll">Deselect All</button>
            <button class="btn btn-danger" id="btnDeleteSelected">Delete Selected</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var csrfToken = '<?= $csrf ?>';
    var duplicatesData = {};

    function loadDuplicates() {
        var container = document.getElementById('duplicatesContainer');
        var loader = document.getElementById('loader');
        loader.style.display = 'block';

        var fd = new FormData();
        fd.append('csrf_token', csrfToken);
        fd.append('action', 'list');

        fetch('/bookmarks/api/duplicates.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                loader.style.display = 'none';
                if (data.success) {
                    duplicatesData = data.duplicates;
                    renderDuplicates(data.duplicates);
                } else {
                    container.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> ' + (data.error || 'Failed to scan library.') + '</div>';
                }
            })
            .catch(function () {
                loader.style.display = 'none';
                container.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> Network request failed.</div>';
            });
    }

    function renderDuplicates(groups) {
        var container = document.getElementById('duplicatesContainer');
        var urls = Object.keys(groups);

        if (urls.length === 0) {
            container.innerHTML = '<div class="text-center py-4"><i class="bi bi-check-circle text-success fs-1"></i><h5 class="mt-3">No Duplicates Found!</h5><p class="text-muted mb-0">Your bookmark collection is clean and has no duplicate URLs.</p></div>';
            document.getElementById('actionBar').classList.add('d-none');
            return;
        }

        var html = '';
        var groupId = 0;

        urls.forEach(function (url) {
            var items = groups[url];
            groupId++;

            html += '<div class="border rounded p-3 mb-4 bg-body-tertiary duplicate-group" data-url-group="' + groupId + '">';
            html += '  <div class="d-flex flex-wrap justify-content-between align-items-center border-bottom pb-2 mb-3">';
            html += '    <div class="text-truncate me-3" style="max-width: 75%;">';
            html += '      <strong class="text-primary"><i class="bi bi-link-45deg"></i> ' + escapeHtml(url) + '</strong>';
            html += '    </div>';
            html += '    <div class="d-flex align-items-center gap-2 mt-2 mt-sm-0">';
            html += '      <button class="btn btn-sm btn-outline-secondary btn-keep-oldest" data-url-group="' + groupId + '">Keep Oldest</button>';
            html += '      <button class="btn btn-sm btn-outline-secondary btn-keep-newest" data-url-group="' + groupId + '">Keep Newest</button>';
            html += '    </div>';
            html += '  </div>';

            items.forEach(function (bm, index) {
                var folder = bm.folder_name ? escapeHtml(bm.folder_name) : 'No folder';
                var tagsHtml = '';
                if (bm.tags) {
                    bm.tags.split(',').forEach(function (t) {
                        tagsHtml += '<span class="badge tag-badge me-1">' + escapeHtml(t.trim()) + '</span>';
                    });
                }

                html += '<div class="row align-items-center py-2 border-bottom border-light-subtle last-no-border">';
                html += '  <div class="col-auto">';
                html += '    <div class="form-check">';
                html += '      <input class="form-check-input duplicate-checkbox" type="checkbox" value="' + bm.id + '" data-url-group="' + groupId + '" data-index="' + index + '">';
                html += '    </div>';
                html += '  </div>';
                html += '  <div class="col">';
                html += '    <div class="d-flex align-items-center">';
                html += '      <span class="fw-semibold text-truncate" style="max-width: 250px;">' + escapeHtml(bm.title) + '</span>';
                html += '      <span class="badge bg-secondary ms-2 small">' + escapeHtml(bm.visibility) + '</span>';
                html += '      <span class="text-muted small ms-3"><i class="bi bi-folder"></i> ' + folder + '</span>';
                html += '    </div>';
                html += '    <div class="mt-1 small text-muted">';
                html += '      Added ' + escapeHtml(bm.created_at) + (tagsHtml ? ' &bull; ' + tagsHtml : '');
                html += '    </div>';
                html += '  </div>';
                html += '</div>';
            });

            html += '</div>';
        });

        container.innerHTML = html;
        updateActionBar();
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // Update selection states
    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('duplicate-checkbox')) {
            updateActionBar();
        }
    });

    function updateActionBar() {
        var checkboxes = document.querySelectorAll('.duplicate-checkbox');
        var checkedCount = 0;
        checkboxes.forEach(function (cb) {
            if (cb.checked) checkedCount++;
        });

        document.getElementById('selectedCount').innerText = checkedCount;

        var actionBar = document.getElementById('actionBar');
        if (checkedCount > 0) {
            actionBar.classList.remove('d-none');
        } else {
            actionBar.classList.add('d-none');
        }
    }

    // Keep oldest/newest operations
    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('btn-keep-oldest')) {
            e.preventDefault();
            var groupId = e.target.dataset.urlGroup;
            var groupCheckboxes = document.querySelectorAll('.duplicate-checkbox[data-url-group="' + groupId + '"]');
            groupCheckboxes.forEach(function (cb, index) {
                cb.checked = (index > 0); // Check all duplicates except the first (oldest)
            });
            updateActionBar();
        }

        if (e.target.classList.contains('btn-keep-newest')) {
            e.preventDefault();
            var groupId = e.target.dataset.urlGroup;
            var groupCheckboxes = document.querySelectorAll('.duplicate-checkbox[data-url-group="' + groupId + '"]');
            groupCheckboxes.forEach(function (cb, index) {
                cb.checked = (index < groupCheckboxes.length - 1); // Check all duplicates except the last (newest)
            });
            updateActionBar();
        }
    });

    // Select all duplicates
    document.getElementById('btnSelectAll').addEventListener('click', function () {
        var groups = document.querySelectorAll('.duplicate-group');
        groups.forEach(function (group) {
            var checkboxes = group.querySelectorAll('.duplicate-checkbox');
            checkboxes.forEach(function (cb, index) {
                cb.checked = (index > 0);
            });
        });
        updateActionBar();
    });

    // Deselect all
    document.getElementById('btnDeselectAll').addEventListener('click', function () {
        var checkboxes = document.querySelectorAll('.duplicate-checkbox');
        checkboxes.forEach(function (cb) {
            cb.checked = false;
        });
        updateActionBar();
    });

    // Submit bulk deletion
    document.getElementById('btnDeleteSelected').addEventListener('click', function () {
        var checked = document.querySelectorAll('.duplicate-checkbox:checked');
        var ids = [];
        checked.forEach(function (cb) {
            ids.push(parseInt(cb.value));
        });

        if (ids.length === 0) return;

        if (!confirm('Are you sure you want to delete ' + ids.length + ' selected duplicate bookmark(s)?')) {
            return;
        }

        var btn = this;
        btn.disabled = true;

        var fd = new FormData();
        fd.append('csrf_token', csrfToken);
        fd.append('action', 'delete_bulk');
        fd.append('ids', JSON.stringify(ids));

        fetch('/bookmarks/api/duplicates.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                btn.disabled = false;
                if (data.success) {
                    loadDuplicates();
                } else {
                    alert(data.error || 'Failed to delete duplicates.');
                }
            })
            .catch(function () {
                btn.disabled = false;
                alert('Request failed');
            });
    });

    // Init scan
    loadDuplicates();
});
</script>

<style>
.last-no-border:last-of-type {
    border-bottom: none !important;
}
body {
    padding-bottom: 90px;
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
