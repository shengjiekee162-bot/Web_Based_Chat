(() => {
  const { api, escapeHtml, toast, presenceClass, timeLabel } = window.WC;
  if (!document.getElementById('friendsList')) return;

  function userRow(user, actionsHtml) {
    return `
      <div class="list-row">
        <img class="avatar-sm" src="${escapeHtml(user.avatar_url)}" alt="">
        <div class="meta">
          <strong>${escapeHtml(user.username)}</strong>
          <small class="presence-dot ${presenceClass(user.presence)}">${escapeHtml(user.presence || 'offline')}${user.status_message ? ' · ' + escapeHtml(user.status_message) : ''}</small>
        </div>
        <div class="d-flex gap-1 flex-wrap">${actionsHtml}</div>
      </div>`;
  }

  async function loadFriends() {
    const res = await api('api/friends.php?action=list');
    const el = document.getElementById('friendsList');
    if (!res.data.length) {
      el.innerHTML = '<div class="text-muted small">No friends yet.</div>';
      return;
    }
    el.innerHTML = res.data.map((u) => userRow(u, `
      <a class="btn btn-sm btn-primary" href="chat.php?user=${u.id}">Chat</a>
      <button class="btn btn-sm btn-outline-primary" data-call="${u.id}" data-type="voice">Call</button>
      <button class="btn btn-sm btn-outline-secondary" data-remove="${u.id}">Remove</button>
      <button class="btn btn-sm btn-outline-danger" data-block="${u.id}">Block</button>
    `)).join('');
    bindFriendActions(el);
  }

  async function loadRequests() {
    const res = await api('api/friends.php?action=requests');
    const inc = document.getElementById('incomingRequests');
    const out = document.getElementById('outgoingRequests');
    inc.innerHTML = res.data.incoming.length ? res.data.incoming.map((r) => userRow(r.user, `
      <button class="btn btn-sm btn-success" data-accept="${r.id}">Accept</button>
      <button class="btn btn-sm btn-outline-secondary" data-reject="${r.id}">Reject</button>
    `)).join('') : '<div class="text-muted small">No incoming requests.</div>';
    out.innerHTML = res.data.outgoing.length ? res.data.outgoing.map((r) => userRow(r.user, `
      <button class="btn btn-sm btn-outline-secondary" data-cancel="${r.id}">Cancel</button>
    `)).join('') : '<div class="text-muted small">No outgoing requests.</div>';
    bindFriendActions(inc);
    bindFriendActions(out);
  }

  async function loadBlocked() {
    const res = await api('api/blocks.php?action=list');
    const el = document.getElementById('blockedList');
    el.innerHTML = res.data.length ? res.data.map((u) => userRow(u, `
      <button class="btn btn-sm btn-outline-primary" data-unblock="${u.id}">Unblock</button>
    `)).join('') : '<div class="text-muted small">No blocked users.</div>';
    bindFriendActions(el);
  }

  function bindFriendActions(root) {
    root.querySelectorAll('[data-accept]').forEach((b) => b.addEventListener('click', async () => {
      await api('api/friends.php?action=accept', { method: 'POST', body: { request_id: Number(b.dataset.accept) } });
      refresh();
    }));
    root.querySelectorAll('[data-reject]').forEach((b) => b.addEventListener('click', async () => {
      await api('api/friends.php?action=reject', { method: 'POST', body: { request_id: Number(b.dataset.reject) } });
      refresh();
    }));
    root.querySelectorAll('[data-cancel]').forEach((b) => b.addEventListener('click', async () => {
      await api('api/friends.php?action=cancel', { method: 'POST', body: { request_id: Number(b.dataset.cancel) } });
      refresh();
    }));
    root.querySelectorAll('[data-remove]').forEach((b) => b.addEventListener('click', async () => {
      if (!confirm('Remove friend?')) return;
      await api('api/friends.php?action=remove', { method: 'POST', body: { user_id: Number(b.dataset.remove) } });
      refresh();
    }));
    root.querySelectorAll('[data-block]').forEach((b) => b.addEventListener('click', async () => {
      if (!confirm('Block user?')) return;
      await api('api/blocks.php?action=block', { method: 'POST', body: { user_id: Number(b.dataset.block) } });
      refresh();
    }));
    root.querySelectorAll('[data-unblock]').forEach((b) => b.addEventListener('click', async () => {
      await api('api/blocks.php?action=unblock', { method: 'POST', body: { user_id: Number(b.dataset.unblock) } });
      refresh();
    }));
    root.querySelectorAll('[data-call]').forEach((b) => b.addEventListener('click', () => {
      window.WCCalls?.startCall(Number(b.dataset.call), b.dataset.type || 'voice');
    }));
    root.querySelectorAll('[data-add]').forEach((b) => b.addEventListener('click', async () => {
      try {
        await api('api/friends.php?action=send', { method: 'POST', body: { user_id: Number(b.dataset.add) } });
        toast('Friend request sent', 'success');
        refresh();
        search();
      } catch (e) { toast(e.message, 'error'); }
    }));
  }

  async function search() {
    const q = document.getElementById('userSearch').value.trim();
    const el = document.getElementById('searchResults');
    if (q.length < 2) { el.innerHTML = ''; return; }
    const res = await api(`api/friends.php?action=search&q=${encodeURIComponent(q)}`);
    el.innerHTML = res.data.map((u) => {
      let actions = '';
      if (u.is_friend) actions = `<a class="btn btn-sm btn-primary" href="chat.php?user=${u.id}">Chat</a>`;
      else if (u.request?.status === 'pending' && u.request.direction === 'outgoing') actions = '<span class="small text-muted">Request sent</span>';
      else if (u.request?.status === 'pending' && u.request.direction === 'incoming') actions = `<button class="btn btn-sm btn-success" data-accept="${u.request.id}">Accept</button>`;
      else actions = `<button class="btn btn-sm btn-outline-primary" data-add="${u.id}">Add friend</button>`;
      return userRow(u, actions);
    }).join('') || '<div class="text-muted small">No users found.</div>';
    bindFriendActions(el);
  }

  function refresh() {
    loadFriends().catch(() => {});
    loadRequests().catch(() => {});
    loadBlocked().catch(() => {});
  }

  document.getElementById('userSearch')?.addEventListener('input', () => {
    clearTimeout(window.__fs);
    window.__fs = setTimeout(search, 300);
  });

  refresh();
})();
