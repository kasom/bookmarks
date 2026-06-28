<?php
// check_links.php - Broken Link Checker
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$user_id = get_current_user_id();
$folders = get_folders($user_id);

$pageTitle = 'Broken Link Checker';
$csrf = generate_csrf_token();
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-exclamation-triangle-fill text-warning"></i> Broken Link Checker</h2>
    <a href="/bookmarks/settings.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Settings</a>
</div>

<div class="row">
    <div class="col-md-4">
        <!-- Scan controls and progress status -->
        <div class="card shadow mb-4">
            <div class="card-header"><h5 class="mb-0">Scan Control</h5></div>
            <div class="card-body">
                <div class="d-grid gap-2 mb-3">
                    <button class="btn btn-primary" id="btnStartScan"><i class="bi bi-play-fill"></i> Start Scan</button>
                    <button class="btn btn-secondary d-none" id="btnPauseScan"><i class="bi bi-pause-fill"></i> Pause Scan</button>
                </div>
                
                <div class="progress mb-3" style="height: 20px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                </div>

                <div class="list-group list-group-flush">
                    <div class="list-group-item d-flex justify-content-between align-items-center bg-transparent border-0 px-0">
                        <span>Total Bookmarks</span>
                        <strong id="statTotal">0</strong>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center bg-transparent border-0 px-0">
                        <span>Checked</span>
                        <strong id="statChecked">0</strong>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center bg-transparent border-0 px-0">
                        <span>Active Links</span>
                        <strong class="text-success" id="statActive">0</strong>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center bg-transparent border-0 px-0">
                        <span>Broken Links</span>
                        <strong class="text-danger" id="statBroken">0</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <!-- Results panel -->
        <div class="card shadow mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Broken Bookmarks</h5>
                <button class="btn btn-sm btn-outline-danger d-none" id="btnDeleteAllBroken"><i class="bi bi-trash-fill"></i> Delete All Broken</button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="brokenTable">
                    <thead>
                        <tr>
                            <th>Bookmark</th>
                            <th>Status/Error</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="brokenContainer">
                        <tr>
                            <td colspan="3" class="text-center text-muted py-4" id="placeholderText">Start scanning to check your links.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Bookmark Modal -->
<div class="modal fade" id="editBookmarkModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Bookmark</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editBookmarkForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label class="form-label">URL *</label>
                        <input type="url" name="url" id="edit_url" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" id="edit_title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Folder</label>
                        <select name="folder_id" id="edit_folder_id" class="form-select">
                            <option value="">No folder</option>
                            <?php foreach ($folders as $f): ?>
                                <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tags</label>
                        <input type="text" name="tags" id="edit_tags" class="form-control" placeholder="tag1, tag2">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Visibility</label>
                        <select name="visibility" id="edit_visibility" class="form-select">
                            <option value="private">Private</option>
                            <option value="public">Public</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var csrfToken = '<?= $csrf ?>';
    
    var queue = [];
    var activeWorkers = 0;
    var maxConcurrent = 5;
    var isScanning = false;
    
    var totalCount = 0;
    var checkedCount = 0;
    var activeCount = 0;
    var brokenCount = 0;
    
    var brokenIds = []; // Track broken bookmark IDs for bulk deletion

    var btnStart = document.getElementById('btnStartScan');
    var btnPause = document.getElementById('btnPauseScan');
    var btnBulkDelete = document.getElementById('btnDeleteAllBroken');
    var progressBar = document.getElementById('progressBar');

    var editModal = new bootstrap.Modal(document.getElementById('editBookmarkModal'));

    // Fetch initial list
    function loadQueue() {
        var fd = new FormData();
        fd.append('csrf_token', csrfToken);
        fd.append('action', 'list');

        fetch('/bookmarks/api/check_links.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    queue = data.bookmarks;
                    totalCount = queue.length;
                    document.getElementById('statTotal').innerText = totalCount;
                } else {
                    alert(data.error || 'Failed to initialize checker');
                }
            })
            .catch(function () {
                alert('Connection failure loading queue');
            });
    }

    function processQueue() {
        if (!isScanning) return;
        
        while (activeWorkers < maxConcurrent && queue.length > 0) {
            var bookmark = queue.shift();
            activeWorkers++;
            checkBookmark(bookmark);
        }

        if (activeWorkers === 0 && queue.length === 0) {
            finishScan();
        }
    }

    function checkBookmark(bm) {
        var fd = new FormData();
        fd.append('csrf_token', csrfToken);
        fd.append('action', 'check');
        fd.append('id', bm.id);

        fetch('/bookmarks/api/check_links.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                activeWorkers--;
                checkedCount++;
                
                if (data.success) {
                    if (data.status === 'ok') {
                        activeCount++;
                    } else {
                        brokenCount++;
                        brokenIds.push(bm.id);
                        addBrokenItem(bm, data.error || 'Broken Link');
                    }
                } else {
                    brokenCount++;
                    brokenIds.push(bm.id);
                    addBrokenItem(bm, data.error || 'Scan Failed');
                }

                updateStats();
                processQueue();
            })
            .catch(function () {
                activeWorkers--;
                checkedCount++;
                brokenCount++;
                brokenIds.push(bm.id);
                addBrokenItem(bm, 'Request Error');
                updateStats();
                processQueue();
            });
    }

    function addBrokenItem(bm, errorText) {
        var placeholder = document.getElementById('placeholderText');
        if (placeholder) {
            placeholder.parentNode.removeChild(placeholder);
        }

        var container = document.getElementById('brokenContainer');
        var tr = document.createElement('tr');
        tr.id = 'broken-row-' + bm.id;
        
        var nameCell = '<td><strong class="text-truncate d-inline-block" style="max-width: 280px;" title="' + escapeHtml(bm.title) + '">' + escapeHtml(bm.title) + '</strong><br><small class="text-muted text-truncate d-inline-block" style="max-width: 320px;"><a href="' + escapeHtml(bm.url) + '" target="_blank">' + escapeHtml(bm.url) + '</a></small></td>';
        var errorCell = '<td><span class="badge bg-danger"><i class="bi bi-x-circle"></i> ' + escapeHtml(errorText) + '</span></td>';
        var actionCell = '<td class="text-end"><button class="btn btn-sm btn-outline-primary edit-btn me-1" data-id="' + bm.id + '"><i class="bi bi-pencil"></i></button><button class="btn btn-sm btn-outline-danger delete-btn" data-id="' + bm.id + '"><i class="bi bi-trash"></i></button></td>';

        tr.innerHTML = nameCell + errorCell + actionCell;
        container.appendChild(tr);
        
        btnBulkDelete.classList.remove('d-none');
    }

    function updateStats() {
        document.getElementById('statChecked').innerText = checkedCount;
        document.getElementById('statActive').innerText = activeCount;
        document.getElementById('statBroken').innerText = brokenCount;

        var percent = totalCount > 0 ? Math.round((checkedCount / totalCount) * 100) : 0;
        progressBar.style.width = percent + '%';
        progressBar.innerText = percent + '%';
        progressBar.setAttribute('aria-valuenow', percent);
    }

    function startScan() {
        if (queue.length === 0 && checkedCount === totalCount) {
            // Reset scan if starting fresh
            checkedCount = 0;
            activeCount = 0;
            brokenCount = 0;
            brokenIds = [];
            document.getElementById('brokenContainer').innerHTML = '';
            btnBulkDelete.classList.add('d-none');
            updateStats();
            loadQueue();
            setTimeout(startScan, 500);
            return;
        }

        isScanning = true;
        btnStart.classList.add('d-none');
        btnPause.classList.remove('d-none');
        processQueue();
    }

    function pauseScan() {
        isScanning = false;
        btnPause.classList.add('d-none');
        btnStart.classList.remove('d-none');
        btnStart.innerHTML = '<i class="bi bi-play-fill"></i> Resume Scan';
    }

    function finishScan() {
        isScanning = false;
        btnPause.classList.add('d-none');
        btnStart.classList.remove('d-none');
        btnStart.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Scan Again';
        
        var container = document.getElementById('brokenContainer');
        if (brokenCount === 0) {
            container.innerHTML = '<tr><td colspan="3" class="text-center text-success py-4"><i class="bi bi-patch-check-fill fs-2"></i><br>All links are fully functional!</td></tr>';
        }
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // Connect trigger events
    btnStart.addEventListener('click', startScan);
    btnPause.addEventListener('click', pauseScan);

    // Delete single broken item
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.delete-btn');
        if (btn) {
            e.preventDefault();
            var id = btn.dataset.id;
            if (!confirm('Are you sure you want to delete this bookmark?')) return;

            var fd = new FormData();
            fd.append('csrf_token', csrfToken);
            fd.append('action', 'delete');
            fd.append('id', id);

            fetch('/bookmarks/api/bookmarks.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        var row = document.getElementById('broken-row-' + id);
                        if (row) row.parentNode.removeChild(row);
                        
                        // Remove from bulk list
                        brokenIds = brokenIds.filter(function (val) { return val != id; });
                        if (brokenIds.length === 0) btnBulkDelete.classList.add('d-none');
                    } else {
                        alert(data.error || 'Failed to delete bookmark');
                    }
                });
        }
    });

    // Bulk Delete Broken Bookmarks
    btnBulkDelete.addEventListener('click', function (e) {
        e.preventDefault();
        if (brokenIds.length === 0) return;
        if (!confirm('Delete all ' + brokenIds.length + ' broken bookmarks?')) return;

        var btn = this;
        btn.disabled = true;

        var fd = new FormData();
        fd.append('csrf_token', csrfToken);
        fd.append('action', 'delete_bulk');
        fd.append('ids', JSON.stringify(brokenIds));

        fetch('/bookmarks/api/duplicates.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                btn.disabled = false;
                if (data.success) {
                    document.getElementById('brokenContainer').innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">All broken bookmarks deleted.</td></tr>';
                    btnBulkDelete.classList.add('d-none');
                    brokenIds = [];
                } else {
                    alert(data.error || 'Bulk deletion failed');
                }
            })
            .catch(function () {
                btn.disabled = false;
                alert('Request failed');
            });
    });

    // Edit modal trigger
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.edit-btn');
        if (btn) {
            e.preventDefault();
            var id = btn.dataset.id;
            
            var fd = new FormData();
            fd.append('csrf_token', csrfToken);
            fd.append('action', 'get');
            fd.append('id', id);

            fetch('/bookmarks/api/bookmarks.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success && data.bookmark) {
                        document.getElementById('edit_id').value = data.bookmark.id;
                        document.getElementById('edit_url').value = data.bookmark.url;
                        document.getElementById('edit_title').value = data.bookmark.title;
                        document.getElementById('edit_description').value = data.bookmark.description || '';
                        document.getElementById('edit_folder_id').value = data.bookmark.folder_id || '';
                        document.getElementById('edit_tags').value = data.bookmark.tags || '';
                        document.getElementById('edit_visibility').value = data.bookmark.visibility;
                        editModal.show();
                    }
                });
        }
    });

    // Submit edit form
    document.getElementById('editBookmarkForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var fd = new FormData(this);
        fd.append('action', 'update');

        fetch('/bookmarks/api/bookmarks.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    editModal.hide();
                    
                    // Remove the updated row from the table since it has been fixed
                    var id = document.getElementById('edit_id').value;
                    var row = document.getElementById('broken-row-' + id);
                    if (row) row.parentNode.removeChild(row);
                    
                    brokenIds = brokenIds.filter(function (val) { return val != id; });
                    if (brokenIds.length === 0) btnBulkDelete.classList.add('d-none');
                } else {
                    alert(data.error || 'Failed to update bookmark');
                }
            });
    });

    // Load initial queue
    loadQueue();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
