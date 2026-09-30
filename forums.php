<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
start_user_session();
require_login();

$pageTitle = 'Community';
$activeNav = 'forums';
$pageScripts = ['forums.js'];
$postId = (int) ($_GET['post'] ?? 0);
$forumId = (int) ($_GET['forum'] ?? 0);
require __DIR__ . '/includes/layout_start.php';
?>
<div class="page-wrap" id="forumsApp" data-post="<?= (int) $postId ?>" data-forum="<?= (int) $forumId ?>">
    <div class="page-header d-flex flex-wrap justify-content-between gap-3">
        <div>
            <h1>Community</h1>
            <p class="text-muted mb-0">Discuss, share, and connect with other members.</p>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newPostModal">
            <i class="fa-solid fa-pen"></i> New post
        </button>
    </div>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="surface-card">
                <h2 class="h6 text-uppercase text-muted">Categories</h2>
                <div id="forumCategories" class="list-stack"></div>
            </div>
        </div>
        <div class="col-md-8">
            <div id="forumMain" class="surface-card">
                <div class="text-muted">Select a category to browse posts.</div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="newPostModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" id="newPostForm">
            <div class="modal-header">
                <h5 class="modal-title">New post</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Category</label>
                    <select name="forum_id" id="newPostForum" class="form-select" required></select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" class="form-control" required maxlength="200">
                </div>
                <div class="mb-0">
                    <label class="form-label">Content</label>
                    <textarea name="content" class="form-control" rows="6" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Publish</button>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/includes/layout_end.php'; ?>
