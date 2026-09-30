(() => {
  const { api, escapeHtml, toast, createRecorder, timeLabel } = window.WC;

  // Groups list page
  if (document.getElementById('groupsList')) {
    async function loadGroups() {
      const res = await api('api/groups.php?action=list');
      const el = document.getElementById('groupsList');
      if (!res.data.length) {
        el.innerHTML = '<div class="text-muted">No groups yet. Create one to get started.</div>';
        return;
      }
      el.innerHTML = res.data.map((g) => `
        <a class="group-card" href="group_chat.php?id=${g.id}">
          <img class="avatar-sm" src="${escapeHtml(g.avatar_url)}" alt="">
          <div>
            <strong>${escapeHtml(g.name)}</strong>
            <div class="small text-muted">${g.member_count || 0} members${g.unread_count ? ` · ${g.unread_count} unread` : ''}</div>
          </div>
        </a>
      `).join('');
    }

    async function loadFriendPicker() {
      const res = await api('api/friends.php?action=list');
      const el = document.getElementById('friendPicker');
      el.innerHTML = res.data.map((u) => `
        <label class="d-flex align-items-center gap-2 mb-1">
          <input type="checkbox" name="member_ids" value="${u.id}">
          <span>${escapeHtml(u.username)}</span>
        </label>
      `).join('') || '<div class="small text-muted">Add friends first.</div>';
    }

    document.getElementById('createGroupForm')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      const member_ids = [...e.target.querySelectorAll('input[name="member_ids"]:checked')].map((i) => Number(i.value));
      try {
        const res = await api('api/groups.php?action=create', {
          method: 'POST',
          body: {
            name: fd.get('name'),
            description: fd.get('description'),
            member_ids,
          },
        });
        location.href = `group_chat.php?id=${res.data.id}`;
      } catch (err) {
        toast(err.message, 'error');
      }
    });

    loadGroups();
    loadFriendPicker();
  }

  // Group chat page
  const layout = document.getElementById('groupChatLayout');
  if (!layout) return;

  const groupId = Number(layout.dataset.group);
  let lastId = 0;
  let replyTo = null;
  let myRole = 'member';
  const reactions = ['👍', '❤️', '😂', '😮', '😢', '👏'];
  window.__gMsgs = [];

  const scroll = document.getElementById('groupMessages');
  const recorder = createRecorder({});

  function renderMsg(m) {
    const div = document.createElement('div');
    div.className = `msg ${m.is_mine ? 'mine' : ''} ${m.is_deleted ? 'deleted' : ''}`;
    let body = m.is_deleted ? '<em>Deleted message</em>' : '';
    if (!m.is_deleted) {
      if (m.message_type === 'image' && m.media_url) body = `<img src="${escapeHtml(m.media_url)}" style="max-width:240px;border-radius:10px" alt="">`;
      else if (m.message_type === 'file' && m.media_url) body = `<a href="${escapeHtml(m.media_url.replace('d=inline', 'd=attachment'))}"><i class="fa-solid fa-paperclip"></i> ${escapeHtml(m.file_name || 'File')}</a>`;
      else if (m.message_type === 'voice' && m.media_url) body = `<audio controls src="${escapeHtml(m.media_url)}"></audio>`;
      else body = escapeHtml(m.body || '');
    }
    const reply = m.reply_to ? `<div class="msg-reply">${escapeHtml(m.reply_to.body)}</div>` : '';
    const reactHtml = (m.reactions || []).map((r) => `<button type="button" class="reaction-chip" data-react="${escapeHtml(r.reaction)}">${escapeHtml(r.reaction)} ${r.count}</button>`).join('');
    div.innerHTML = `
      ${!m.is_mine ? `<div class="small text-muted mb-1">${escapeHtml(m.sender.username)}</div>` : ''}
      ${reply}<div>${body}</div><div class="reactions">${reactHtml}</div>
      <div class="msg-meta"><span>${timeLabel(m.created_at)}</span>${m.edited_at ? '<span class="msg-edited"> · edited</span>' : ''}${m.is_mine && m.read_count != null ? `<span>· Read by ${m.read_count}</span>` : ''}</div>
      ${m.is_deleted ? '' : `<div class="msg-actions">
        <button type="button" class="btn btn-sm btn-light" data-act="reply">Reply</button>
        ${reactions.map((r) => `<button type="button" class="btn btn-sm btn-light" data-act="react" data-emoji="${r}">${r}</button>`).join('')}
        ${(m.is_mine || myRole === 'admin' || myRole === 'owner') ? '<button type="button" class="btn btn-sm btn-light text-danger" data-act="delete">Delete</button>' : ''}
      </div>`}
    `;
    div.querySelectorAll('[data-act]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        if (btn.dataset.act === 'reply') {
          replyTo = m.id;
          document.getElementById('groupReplyPreview').textContent = m.body || m.message_type;
          document.getElementById('groupReplyBar').classList.remove('d-none');
        } else if (btn.dataset.act === 'react') {
          const res = await api('api/reactions.php?action=toggle', { method: 'POST', body: { scope: 'group', message_id: m.id, reaction: btn.dataset.emoji } });
          m.reactions = res.data.reactions;
          paint();
        } else if (btn.dataset.act === 'delete') {
          await api('api/groups.php?action=delete_message', { method: 'POST', body: { message_id: m.id } });
          m.is_deleted = true;
          paint();
        }
      });
    });
    div.querySelectorAll('[data-react]').forEach((btn) => btn.addEventListener('click', async () => {
      const res = await api('api/reactions.php?action=toggle', { method: 'POST', body: { scope: 'group', message_id: m.id, reaction: btn.dataset.react } });
      m.reactions = res.data.reactions;
      paint();
    }));
    return div;
  }

  function paint() {
    const atBottom = scroll.scrollHeight - scroll.scrollTop - scroll.clientHeight < 80;
    scroll.innerHTML = '';
    window.__gMsgs.forEach((m) => scroll.appendChild(renderMsg(m)));
    if (atBottom) scroll.scrollTop = scroll.scrollHeight;
  }

  function appendGroupMessages(list) {
    if (!list?.length) return;
    const atBottom = scroll.scrollHeight - scroll.scrollTop - scroll.clientHeight < 80;
    list.forEach((m) => {
      if (scroll.querySelector(`.msg[data-id="${m.id}"]`)) return;
      const node = renderMsg(m);
      node.classList.add('msg-new');
      scroll.appendChild(node);
    });
    if (atBottom) scroll.scrollTop = scroll.scrollHeight;
  }

  async function loadMeta() {
    const res = await api(`api/groups.php?action=get&id=${groupId}`);
    const g = res.data.group;
    myRole = g.role;
    document.getElementById('groupName').textContent = g.name;
    document.getElementById('groupAvatar').src = g.avatar_url;
    document.getElementById('groupMeta').textContent = `${res.data.members.length} members · You are ${g.role}`;
    document.getElementById('editGroupName').value = g.name;
    document.getElementById('editGroupDesc').value = g.description || '';
    const canManage = g.role === 'owner' || g.role === 'admin';
    document.getElementById('btnSaveGroup').disabled = !canManage;
    document.getElementById('btnDeleteGroup').classList.toggle('d-none', g.role !== 'owner');

    const list = document.getElementById('groupMembersList');
    list.innerHTML = res.data.members.map((m) => `
      <div class="list-row">
        <img class="avatar-sm" src="${escapeHtml(m.avatar_url)}" alt="">
        <div class="meta"><strong>${escapeHtml(m.username)}</strong><small>${escapeHtml(m.role)}</small></div>
        <div class="d-flex gap-1">
          ${g.role === 'owner' && m.role !== 'owner' ? `
            <button class="btn btn-sm btn-outline-secondary" data-role="${m.id}" data-set="${m.role === 'admin' ? 'member' : 'admin'}">${m.role === 'admin' ? 'Demote' : 'Make admin'}</button>
            <button class="btn btn-sm btn-outline-primary" data-transfer="${m.id}">Make owner</button>
            <button class="btn btn-sm btn-outline-danger" data-kick="${m.id}">Remove</button>` : ''}
          ${g.role === 'admin' && m.role === 'member' ? `<button class="btn btn-sm btn-outline-danger" data-kick="${m.id}">Remove</button>` : ''}
        </div>
      </div>
    `).join('');
    list.querySelectorAll('[data-kick]').forEach((b) => b.addEventListener('click', async () => {
      await api('api/groups.php?action=remove_member', { method: 'POST', body: { group_id: groupId, user_id: Number(b.dataset.kick) } });
      loadMeta();
    }));
    list.querySelectorAll('[data-role]').forEach((b) => b.addEventListener('click', async () => {
      await api('api/groups.php?action=set_role', { method: 'POST', body: { group_id: groupId, user_id: Number(b.dataset.role), role: b.dataset.set } });
      loadMeta();
    }));
    list.querySelectorAll('[data-transfer]').forEach((b) => b.addEventListener('click', async () => {
      if (!confirm('Transfer group ownership to this member? You will become an admin.')) return;
      await api('api/groups.php?action=transfer', { method: 'POST', body: { group_id: groupId, user_id: Number(b.dataset.transfer) } });
      toast('Ownership transferred', 'success');
      loadMeta();
    }));

    const friends = await api('api/friends.php?action=list');
    const memberIds = new Set(res.data.members.map((m) => m.id));
    const sel = document.getElementById('addMemberSelect');
    sel.innerHTML = friends.data.filter((f) => !memberIds.has(f.id)).map((f) => `<option value="${f.id}">${escapeHtml(f.username)}</option>`).join('');
  }

  async function loadMessages(after = 0) {
    const res = await api(`api/groups.php?action=messages&group_id=${groupId}&after_id=${after}`);
    const incoming = [];
    if (!after) {
      window.__gMsgs = [];
      (res.data.messages || []).forEach((m) => {
        window.__gMsgs.push(m);
        lastId = Math.max(lastId, m.id);
      });
      paint();
    } else {
      (res.data.messages || []).forEach((m) => {
        if (!window.__gMsgs.find((x) => x.id === m.id)) {
          window.__gMsgs.push(m);
          incoming.push(m);
        }
        lastId = Math.max(lastId, m.id);
      });
      appendGroupMessages(incoming);
    }
    const typing = res.data.typing || [];
    const tEl = document.getElementById('groupTyping');
    if (typing.length) {
      tEl.classList.remove('d-none');
      tEl.textContent = typing.map((t) => t.username).join(', ') + ' typing...';
    } else tEl.classList.add('d-none');
  }

  document.getElementById('groupComposer')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const input = document.getElementById('groupInput');
    const body = input.value.trim();
    if (!body) return;
    try {
      const res = await api('api/groups.php?action=send', {
        method: 'POST',
        body: { group_id: groupId, message_type: 'text', body, reply_to_id: replyTo || 0 },
      });
      window.__gMsgs.push(res.data);
      lastId = Math.max(lastId, res.data.id);
      input.value = '';
      replyTo = null;
      document.getElementById('groupReplyBar').classList.add('d-none');
      appendGroupMessages([res.data]);
    } catch (err) { toast(err.message, 'error'); }
  });

  document.getElementById('groupInput')?.addEventListener('input', () => {
    api('api/groups.php?action=typing', { method: 'POST', body: { group_id: groupId } }).catch(() => {});
  });
  document.getElementById('groupCancelReply')?.addEventListener('click', () => {
    replyTo = null;
    document.getElementById('groupReplyBar').classList.add('d-none');
  });

  async function sendFile(file, type) {
    const fd = new FormData();
    fd.append('group_id', groupId);
    fd.append('message_type', type);
    fd.append(type === 'image' ? 'image' : type === 'voice' ? 'voice' : 'file', file);
    if (replyTo) fd.append('reply_to_id', replyTo);
    if (file._duration) fd.append('voice_duration', file._duration);
    const res = await api('api/groups.php?action=send', { method: 'POST', body: fd });
    window.__gMsgs.push(res.data);
    lastId = Math.max(lastId, res.data.id);
    appendGroupMessages([res.data]);
  }

  document.getElementById('gImageInput')?.addEventListener('change', (e) => { sendFile(e.target.files[0], 'image'); e.target.value = ''; });
  document.getElementById('gFileInput')?.addEventListener('change', (e) => { sendFile(e.target.files[0], 'file'); e.target.value = ''; });

  let rec = false;
  document.getElementById('gBtnVoice')?.addEventListener('click', async () => {
    try {
      await recorder.start(document.getElementById('gVoiceTimer'));
      rec = true;
      document.getElementById('gVoiceBar').classList.remove('d-none');
    } catch { toast('Microphone permission required.', 'error'); }
  });
  document.getElementById('gCancelVoice')?.addEventListener('click', () => {
    recorder.cancel(); rec = false; document.getElementById('gVoiceBar').classList.add('d-none');
  });
  document.getElementById('gSendVoice')?.addEventListener('click', async () => {
    const r = await recorder.stop();
    rec = false;
    document.getElementById('gVoiceBar').classList.add('d-none');
    if (!r) return;
    const file = new File([r.blob], 'voice.webm', { type: r.blob.type || 'audio/webm' });
    file._duration = r.duration;
    await sendFile(file, 'voice');
  });

  document.getElementById('groupSettingsForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    await api('api/groups.php?action=update', {
      method: 'POST',
      body: { id: groupId, name: document.getElementById('editGroupName').value, description: document.getElementById('editGroupDesc').value },
    });
    const av = document.getElementById('editGroupAvatar').files[0];
    if (av) {
      const fd = new FormData();
      fd.append('id', groupId);
      fd.append('avatar', av);
      await api('api/groups.php?action=avatar', { method: 'POST', body: fd });
    }
    toast('Group updated', 'success');
    loadMeta();
  });

  document.getElementById('btnAddMember')?.addEventListener('click', async () => {
    const userId = Number(document.getElementById('addMemberSelect').value);
    if (!userId) return;
    await api('api/groups.php?action=add_member', { method: 'POST', body: { group_id: groupId, user_id: userId } });
    loadMeta();
  });
  document.getElementById('btnLeaveGroup')?.addEventListener('click', async () => {
    if (!confirm('Leave this group?')) return;
    await api('api/groups.php?action=leave', { method: 'POST', body: { group_id: groupId } });
    location.href = 'groups.php';
  });
  document.getElementById('btnDeleteGroup')?.addEventListener('click', async () => {
    if (!confirm('Delete this group permanently?')) return;
    await api('api/groups.php?action=delete', { method: 'POST', body: { group_id: groupId } });
    location.href = 'groups.php';
  });

  loadMeta().catch((e) => toast(e.message, 'error'));
  loadMessages().catch((e) => toast(e.message, 'error'));
  setInterval(() => loadMessages(lastId).catch(() => {}), window.APP.poll.messages);
})();
