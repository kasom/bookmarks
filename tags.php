<?php
// tags.php - Tag Manager
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$user_id = get_current_user_id();

$tags = get_all_tags($user_id);
$pageTitle = 'Manage Tags';
$csrf = generate_csrf_token();
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-tags"></i> Tag Manager</h2>
    <a href="/bookmarks/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
</div>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header"><h5 class="mb-0">Your Tags</h5></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Tag Name</th>
                            <th>Usage (Bookmarks)</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tags)): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">No tags found. Add tags when creating or editing bookmarks!</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tags as $t): ?>
                                <tr>
                                    <td>
                                        <span class="badge tag-badge fs-6"><?= h($t['tag_name']) ?></span>
                                    </td>
                                    <td><?= (int)$t['count'] ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary me-1 rename-tag-btn" data-tag="<?= h($t['tag_name']) ?>">
                                            <i class="bi bi-pencil"></i> Rename
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger delete-tag-btn" data-tag="<?= h($t['tag_name']) ?>">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Rename Tag Modal -->
<div class="modal fade" id="renameTagModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Rename Tag</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="renameTagForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="old_tag" id="rename_old_tag">
                    <div class="mb-3">
                        <label class="form-label">New Name</label>
                        <input type="text" name="new_tag" id="rename_new_tag" class="form-control" required placeholder="Tag name">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var csrfToken = '<?= $csrf ?>';
    
    // Rename Tag Modal Trigger
    var renameModal = new bootstrap.Modal(document.getElementById('renameTagModal'));
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.rename-tag-btn');
        if (btn) {
            e.preventDefault();
            var tag = btn.dataset.tag;
            document.getElementById('rename_old_tag').value = tag;
            document.getElementById('rename_new_tag').value = tag;
            renameModal.show();
        }
    });

    // Submit Rename Form
    document.getElementById('renameTagForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var fd = new FormData(this);
        fd.append('action', 'rename');
        
        fetch('/bookmarks/api/tags.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    renameModal.hide();
                    location.reload();
                } else {
                    alert(data.error || 'Failed to rename tag');
                }
            })
            .catch(function () {
                alert('Request failed');
            });
    });

    // Delete Tag action
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.delete-tag-btn');
        if (btn) {
            e.preventDefault();
            var tag = btn.dataset.tag;
            if (!confirm('Are you sure you want to delete the tag "' + tag + '" from all bookmarks?')) {
                return;
            }
            
            var fd = new FormData();
            fd.append('csrf_token', csrfToken);
            fd.append('action', 'delete');
            fd.append('tag_name', tag);
            
            fetch('/bookmarks/api/tags.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.error || 'Failed to delete tag');
                    }
                })
                .catch(function () {
                    alert('Request failed');
                });
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
