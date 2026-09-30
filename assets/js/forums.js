(() => {
  const { api, escapeHtml, toast, timeLabel } = window.WC;
  const app = document.getElementById('forumsApp');
  if (!app) return;

  let forumId = Number(app.dataset.forum || 0);
  let postId = Number(app.dataset.post || 0);
  const main = document.getElementById('forumMain');

  async function loadCategories() {
    const res = await api('api/forums.php?action=categories');
    const el = document.getElementById('forumCategories');
    const sel = document.getElementById('newPostForum');
    el.innerHTML = res.data.map((f) => `
      <button type="button" class="list-row w-100 text-start ${forumId === Number(f.id) ? 'border-primary' : ''}" data-forum="${f.id}">
        <div class="meta">
          <strong>${escapeHtml(f.name)}</strong>
          <small>${escapeHtml(f.description || '')} · ${f.post_count} posts</small>
        </div>
      </button>
    `).join('');
    sel.innerHTML = res.data.map((f) => `<option value="${f.id}">${escapeHtml(f.name)}</option>`).join('');
    el.querySelectorAll('[data-forum]').forEach((b) => b.addEventListener('click', () => {
      forumId = Number(b.dataset.forum);
      postId = 0;
      history.replaceState(null, '', `forums.php?forum=${forumId}`);
      loadPosts();
      loadCategories();
    }));
  }

  async function loadPosts() {
    if (!forumId) {
      main.innerHTML = '<div class="text-muted">Select a category to browse posts.</div>';
      return;
    }
    const res = await api(`api/forums.php?action=posts&forum_id=${forumId}`);
    main.innerHTML = `
      <h2 class="h5 mb-3">Posts</h2>
      ${res.data.length ? res.data.map((p) => `
        <button type="button" class="list-row w-100 text-start mb-2" data-post="${p.id}">
          <div class="meta">
            <strong>${escapeHtml(p.title)}</strong>
            <small>by ${escapeHtml(p.author.username)} · ${timeLabel(p.created_at)} · ${p.like_count} likes · ${p.comment_count} comments</small>
          </div>
        </button>
      `).join('') : '<div class="text-muted">No posts yet.</div>'}
    `;
    main.querySelectorAll('[data-post]').forEach((b) => b.addEventListener('click', () => {
      postId = Number(b.dataset.post);
      history.replaceState(null, '', `forums.php?post=${postId}`);
      loadPost();
    }));
  }

  async function loadPost() {
    const res = await api(`api/forums.php?action=post&id=${postId}`);
    const p = res.data.post;
    forumId = p.forum_id;
    main.innerHTML = `
      <button type="button" class="btn btn-link px-0 mb-2" id="backPosts"><i class="fa-solid fa-arrow-left"></i> Back</button>
      <h2 class="h4">${escapeHtml(p.title)}</h2>
      <div class="small text-muted mb-3">by ${escapeHtml(p.author.username)} · ${timeLabel(p.created_at)}</div>
      <div class="mb-3" style="white-space:pre-wrap">${escapeHtml(p.content)}</div>
      <div class="d-flex gap-2 mb-4">
        <button type="button" class="btn btn-sm ${p.liked ? 'btn-primary' : 'btn-outline-primary'}" id="btnLike"><i class="fa-solid fa-heart"></i> ${p.like_count}</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnShare"><i class="fa-solid fa-share"></i> Share</button>
        <button type="button" class="btn btn-sm btn-outline-danger" id="btnReportPost"><i class="fa-solid fa-flag"></i> Report</button>
      </div>
      <h3 class="h6">Comments</h3>
      <div id="commentList" class="mb-3">
        ${res.data.comments.map((c) => `
          <div class="border rounded p-2 mb-2">
            <strong>${escapeHtml(c.user.username)}</strong>
            <span class="small text-muted">${timeLabel(c.created_at)}</span>
            <div>${escapeHtml(c.content)}</div>
          </div>
        `).join('') || '<div class="text-muted small">No comments yet.</div>'}
      </div>
      ${p.is_locked ? '<div class="alert alert-secondary py-2">This post is locked.</div>' : `
      <form id="commentForm" class="d-flex gap-2">
        <input type="text" class="form-control" id="commentInput" placeholder="Write a comment..." required>
        <button class="btn btn-primary">Post</button>
      </form>`}
    `;
    document.getElementById('backPosts')?.addEventListener('click', () => {
      postId = 0;
      history.replaceState(null, '', `forums.php?forum=${forumId}`);
      loadPosts();
    });
    document.getElementById('btnLike')?.addEventListener('click', async () => {
      await api('api/forums.php?action=like', { method: 'POST', body: { post_id: postId } });
      loadPost();
    });
    document.getElementById('btnShare')?.addEventListener('click', async () => {
      const r = await api('api/forums.php?action=share', { method: 'POST', body: { post_id: postId } });
      try {
        await navigator.clipboard.writeText(r.data.share_url);
        toast('Share link copied', 'success');
      } catch {
        prompt('Copy share link:', r.data.share_url);
      }
    });
    document.getElementById('btnReportPost')?.addEventListener('click', async () => {
      const reason = prompt('Why are you reporting this post?');
      if (!reason) return;
      await api('api/forums.php?action=report', { method: 'POST', body: { target_type: 'forum_post', target_id: postId, reason } });
      toast('Report submitted', 'success');
    });
    document.getElementById('commentForm')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      await api('api/forums.php?action=comment', {
        method: 'POST',
        body: { post_id: postId, content: document.getElementById('commentInput').value },
      });
      loadPost();
    });
  }

  document.getElementById('newPostForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    try {
      const res = await api('api/forums.php?action=create', {
        method: 'POST',
        body: { forum_id: Number(fd.get('forum_id')), title: fd.get('title'), content: fd.get('content') },
      });
      bootstrap.Modal.getInstance(document.getElementById('newPostModal')).hide();
      postId = res.data.id;
      history.replaceState(null, '', `forums.php?post=${postId}`);
      loadPost();
      loadCategories();
    } catch (err) { toast(err.message, 'error'); }
  });

  loadCategories().then(() => {
    if (postId) loadPost();
    else if (forumId) loadPosts();
  });
})();
