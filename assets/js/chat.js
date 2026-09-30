(() => {
  const { api, apiUpload, escapeHtml, timeLabel, presenceClass, toast, createRecorder } = window.WC;
  const layout = document.getElementById('chatLayout');
  if (!layout) return;

  let peerId = Number(layout.dataset.peer || 0);
  let lastMsgId = 0;
  let replyTo = null;
  let pollTimer = null;
  let typingTimer = null;
  let searchQuery = '';
  let loadingOlder = false;
  let hasMoreOlder = true;
  const reactions = ['👍', '❤️', '😂', '😮', '😢', '👏'];

  function linkify(text) {
    const escaped = escapeHtml(text);
    return escaped.replace(
      /(https?:\/\/[^\s<]+)/g,
      '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>'
    );
  }

  function openLightbox(src) {
    const box = document.getElementById('lightbox');
    const img = document.getElementById('lightboxImg');
    if (!box || !img) return;
    img.src = src;
    box.classList.remove('d-none');
  }

  const els = {
    list: document.getElementById('convList'),
    search: document.getElementById('convSearch'),
    empty: document.getElementById('chatEmpty'),
    thread: document.getElementById('chatThread'),
    scroll: document.getElementById('messagesScroll'),
    peerAvatar: document.getElementById('peerAvatar'),
    peerName: document.getElementById('peerName'),
    peerStatus: document.getElementById('peerStatus'),
    infoAvatar: document.getElementById('infoAvatar'),
    infoName: document.getElementById('infoName'),
    infoStatusMsg: document.getElementById('infoStatusMsg'),
    infoPresence: document.getElementById('infoPresence'),
    typing: document.getElementById('typingLine'),
    form: document.getElementById('composerForm'),
    input: document.getElementById('messageInput'),
    replyBar: document.getElementById('replyBar'),
    replyPreview: document.getElementById('replyPreview'),
    emojiPicker: document.getElementById('emojiPicker'),
    voiceBar: document.getElementById('voiceRecBar'),
    voiceTimer: document.getElementById('voiceTimer'),
    sidebar: document.getElementById('chatSidebar'),
    main: document.getElementById('chatMain'),
    searchBar: document.getElementById('chatSearchBar'),
    msgSearch: document.getElementById('msgSearchInput'),
    uploadProgress: document.getElementById('uploadProgress'),
    uploadFill: document.getElementById('uploadBarFill'),
    uploadPct: document.getElementById('uploadPct'),
  };

  const recorder = createRecorder({});

  function showUploadProgress(pct) {
    if (!els.uploadProgress) return;
    els.uploadProgress.classList.remove('d-none');
    els.uploadFill.style.width = pct + '%';
    els.uploadPct.textContent = pct + '%';
    if (pct >= 100) setTimeout(() => els.uploadProgress.classList.add('d-none'), 500);
  }

  function renderMessage(m, { animate = false } = {}) {
    const div = document.createElement('div');
    div.className = `msg ${m.is_mine ? 'mine' : ''} ${m.is_deleted ? 'deleted' : ''} ${m.message_type === 'call' ? 'msg-call' : ''}${animate ? ' msg-new' : ''}`;
    div.dataset.id = m.id;
    if (searchQuery && (m.body || '').toLowerCase().includes(searchQuery.toLowerCase())) {
      div.classList.add('highlight-hit');
    }

    let body = '';
    if (m.is_deleted) {
      body = '<em>Deleted message</em>';
    } else if (m.message_type === 'image' && m.media_url) {
      body = `<button type="button" class="btn p-0 border-0 bg-transparent" data-lightbox="${escapeHtml(m.media_url)}"><img src="${escapeHtml(m.media_url)}" alt="" style="max-width:240px;border-radius:10px"></button>`;
    } else if (m.message_type === 'file' && m.media_url) {
      body = `<a href="${escapeHtml(m.media_url.replace('d=inline', 'd=attachment'))}" target="_blank" rel="noopener"><i class="fa-solid fa-paperclip"></i> ${escapeHtml(m.file_name || 'File')}</a>`;
    } else if (m.message_type === 'voice' && m.media_url) {
      body = `<audio controls preload="none" src="${escapeHtml(m.media_url)}" onerror="this.replaceWith(Object.assign(document.createElement('em'),{textContent:'Voice unavailable'}))"></audio>`;
    } else if (m.message_type === 'call') {
      const icon = (m.body || '').toLowerCase().includes('video') ? 'fa-video' : 'fa-phone';
      body = `<div class="msg-call-chip"><i class="fa-solid ${icon}"></i> ${escapeHtml(m.body || 'Call')}</div>`;
    } else {
      body = linkify(m.body || '');
    }

    let reply = '';
    if (m.reply_to) {
      reply = `<div class="msg-reply">${escapeHtml(m.reply_to.body || 'Message')}</div>`;
    }

    let status = '';
    if (m.is_mine && !m.is_deleted) {
      const tick = m.delivery_status === 'read' ? '✓✓ Read' : m.delivery_status === 'delivered' ? '✓✓' : '✓';
      status = `<span class="read-ticks ${m.delivery_status || ''}">${tick}</span>`;
    }
    const edited = m.edited_at ? '<span class="msg-edited"> · edited</span>' : '';

    const reactHtml = (m.reactions || []).map((r) => {
      const active = (r.user_ids || []).includes(window.APP.userId) ? 'active' : '';
      return `<button type="button" class="reaction-chip ${active}" data-react="${escapeHtml(r.reaction)}">${escapeHtml(r.reaction)} ${r.count}</button>`;
    }).join('');

    div.innerHTML = `
      ${reply}
      <div class="msg-body">${body}</div>
      <div class="reactions">${reactHtml}</div>
      <div class="msg-meta">
        <span>${timeLabel(m.created_at)}</span>${edited}
        ${status}
      </div>
      ${m.is_deleted || m.message_type === 'call' ? '' : `
      <div class="msg-actions">
        <button type="button" class="btn btn-sm btn-light" data-act="reply">Reply</button>
        ${m.is_mine && m.message_type === 'text' ? '<button type="button" class="btn btn-sm btn-light" data-act="edit">Edit</button>' : ''}
        ${reactions.map((r) => `<button type="button" class="btn btn-sm btn-light" data-act="react" data-emoji="${r}">${r}</button>`).join('')}
        ${m.is_mine ? '<button type="button" class="btn btn-sm btn-light text-danger" data-act="delete">Delete</button>' : ''}
        <button type="button" class="btn btn-sm btn-light" data-act="report">Report</button>
      </div>`}
    `;

    bindMessageActions(div, m);
    return div;
  }

  function bindMessageActions(div, m) {
    div.querySelector('[data-lightbox]')?.addEventListener('click', () => {
      openLightbox(div.querySelector('[data-lightbox]').dataset.lightbox);
    });

    let pressTimer = null;
    div.addEventListener('touchstart', () => {
      pressTimer = setTimeout(() => {
        const actions = div.querySelector('.msg-actions');
        if (actions) actions.style.display = 'flex';
      }, 480);
    }, { passive: true });
    div.addEventListener('touchend', () => clearTimeout(pressTimer));
    div.addEventListener('touchmove', () => clearTimeout(pressTimer));

    div.querySelectorAll('[data-act]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const act = btn.dataset.act;
        if (act === 'reply') {
          replyTo = m.id;
          els.replyPreview.textContent = m.is_deleted ? 'Deleted message' : (m.body || m.message_type);
          els.replyBar.classList.remove('d-none');
        } else if (act === 'edit') {
          const next = prompt('Edit message', m.body || '');
          if (next === null) return;
          try {
            const res = await api('api/messages.php?action=edit', { method: 'POST', body: { message_id: m.id, body: next } });
            Object.assign(m, res.data);
            replaceMessageNode(m);
          } catch (e) { toast(e.message, 'error'); }
        } else if (act === 'react') {
          await toggleReaction(m.id, btn.dataset.emoji);
        } else if (act === 'delete') {
          if (!confirm('Delete this message?')) return;
          await api('api/messages.php?action=delete', { method: 'POST', body: { message_id: m.id } });
          m.is_deleted = true;
          replaceMessageNode(m);
        } else if (act === 'report') {
          openReport('private_message', m.id);
        }
      });
    });
    div.querySelectorAll('[data-react]').forEach((btn) => {
      btn.addEventListener('click', () => toggleReaction(m.id, btn.dataset.react));
    });
  }

  function replaceMessageNode(m) {
    const old = els.scroll.querySelector(`.msg[data-id="${m.id}"]`);
    const node = renderMessage(m, { animate: false });
    if (old) old.replaceWith(node);
    else els.scroll.appendChild(node);
  }

  function appendMessages(list, { animate = true } = {}) {
    if (!list?.length) return;
    const atBottom = els.scroll.scrollHeight - els.scroll.scrollTop - els.scroll.clientHeight < 80;
    list.forEach((m) => {
      if (els.scroll.querySelector(`.msg[data-id="${m.id}"]`)) return;
      els.scroll.appendChild(renderMessage(m, { animate }));
    });
    if (atBottom && !searchQuery) els.scroll.scrollTop = els.scroll.scrollHeight;
  }

  async function toggleReaction(messageId, reaction) {
    const res = await api('api/reactions.php?action=toggle', {
      method: 'POST',
      body: { scope: 'private', message_id: messageId, reaction },
    });
    const existing = window.__chatMsgs?.find((x) => x.id === messageId);
    if (existing) {
      existing.reactions = res.data.reactions;
      replaceMessageNode(existing);
    }
  }

  function renderOpen() {
    const atBottom = els.scroll.scrollHeight - els.scroll.scrollTop - els.scroll.clientHeight < 80;
    let msgs = window.__chatMsgs || [];
    if (searchQuery) {
      const q = searchQuery.toLowerCase();
      msgs = msgs.filter((m) => (m.body || '').toLowerCase().includes(q) || m.message_type.includes(q));
    }
    els.scroll.innerHTML = '';
    msgs.forEach((m) => els.scroll.appendChild(renderMessage(m, { animate: false })));
    if (atBottom && !searchQuery) els.scroll.scrollTop = els.scroll.scrollHeight;
  }

  let convFingerprint = '';
  async function loadConversations(q = '') {
    const res = await api('api/chat.php?action=conversations' + (q ? `&q=${encodeURIComponent(q)}` : ''));
    const fp = JSON.stringify((res.data || []).map((c) => [c.peer.id, c.last_message?.id, c.unread_count, c.last_message?.body]));
    if (fp === convFingerprint && !q) return;
    convFingerprint = fp;
    els.list.innerHTML = '';
    if (!res.data.length) {
      els.list.innerHTML = '<div class="text-muted p-3 small">No conversations yet. Start from Friends.</div>';
      return;
    }
    res.data.forEach((c) => {
      const item = document.createElement('div');
      item.className = `conv-item ${c.peer.id === peerId ? 'active' : ''}`;
      item.innerHTML = `
        <img class="avatar-sm" src="${escapeHtml(c.peer.avatar_url)}" alt="">
        <div class="meta flex-grow-1" style="min-width:0">
          <strong>${escapeHtml(c.peer.username)}</strong>
          <div class="preview">${escapeHtml(c.last_message.body || '')}</div>
        </div>
        ${c.unread_count ? `<span class="unread-badge">${c.unread_count}</span>` : ''}
      `;
      item.addEventListener('click', () => openChat(c.peer.id));
      els.list.appendChild(item);
    });
  }

  async function openChat(id) {
    peerId = id;
    history.replaceState(null, '', `chat.php?user=${id}`);
    els.empty.classList.add('d-none');
    els.thread.classList.remove('d-none');
    els.main.classList.remove('empty-state');
    mobileShowThread();

    const res = await api(`api/chat.php?action=history&user_id=${id}`);
    window.__chatMsgs = res.data.messages || [];
    lastMsgId = window.__chatMsgs.reduce((max, m) => Math.max(max, typeof m.id === 'number' ? m.id : 0), 0);
    hasMoreOlder = (window.__chatMsgs.length || 0) >= 40;
    setPeerUi(res.data.peer);
    renderOpen();
    startPoll();
    convFingerprint = '';
    loadConversations(els.search.value.trim());
    const exportBtn = document.getElementById('btnExportChat');
    if (exportBtn) exportBtn.href = `${window.APP.url}/api/chat.php?action=export&user_id=${id}`;
  }

  function setPeerUi(peer) {
    if (!peer) return;
    const statusText = peer.presence === 'online' ? 'Online' : (peer.last_seen_at ? `Last seen ${timeLabel(peer.last_seen_at)}` : 'Offline');
    const statusClass = `text-muted presence-dot ${presenceClass(peer.presence)}`;
    if (els.peerAvatar.getAttribute('src') !== peer.avatar_url) els.peerAvatar.src = peer.avatar_url;
    if (els.peerName.textContent !== peer.username) els.peerName.textContent = peer.username;
    if (els.peerStatus.textContent !== statusText) els.peerStatus.textContent = statusText;
    if (els.peerStatus.className !== statusClass) els.peerStatus.className = statusClass;
    if (els.infoAvatar.getAttribute('src') !== peer.avatar_url) els.infoAvatar.src = peer.avatar_url;
    if (els.infoName.textContent !== peer.username) els.infoName.textContent = peer.username;
    if (els.infoStatusMsg.textContent !== (peer.status_message || '')) els.infoStatusMsg.textContent = peer.status_message || '';
    if (els.infoPresence.textContent !== statusText) els.infoPresence.textContent = statusText;
    window.__peer = peer;
  }

  function updateStatusTicks(m) {
    const node = els.scroll.querySelector(`.msg[data-id="${m.id}"]`);
    if (!node) return;
    const tickEl = node.querySelector('.read-ticks');
    if (!tickEl || m.is_deleted) {
      if (m.is_deleted || m.edited_at) replaceMessageNode(m);
      return;
    }
    const tick = m.delivery_status === 'read' ? '✓✓ Read' : m.delivery_status === 'delivered' ? '✓✓' : '✓';
    tickEl.textContent = tick;
    tickEl.className = `read-ticks ${m.delivery_status || ''}`;
  }

  async function refreshStatuses() {
    const mine = (window.__chatMsgs || []).filter((m) => m.is_mine && !m.is_deleted && m.delivery_status !== 'read');
    if (!mine.length) return;
    const ids = mine.map((m) => m.id).join(',');
    try {
      const res = await api(`api/messages.php?action=statuses&ids=${ids}`);
      (res.data || []).forEach((s) => {
        const m = window.__chatMsgs.find((x) => x.id === s.id);
        if (!m) return;
        if (m.delivery_status !== s.delivery_status || m.edited_at !== s.edited_at || m.is_deleted !== s.is_deleted) {
          m.delivery_status = s.delivery_status;
          m.edited_at = s.edited_at;
          m.is_deleted = s.is_deleted;
          if (s.body !== undefined && !s.is_deleted) m.body = s.body;
          updateStatusTicks(m);
        }
      });
    } catch {}
  }

  function startPoll() {
    clearInterval(pollTimer);
    pollTimer = setInterval(async () => {
      if (!peerId) return;
      try {
        const res = await api(`api/chat.php?action=poll&user_id=${peerId}&after_id=${lastMsgId}`);
        const incoming = [];
        if (res.data.messages?.length) {
          window.__chatMsgs = window.__chatMsgs || [];
          res.data.messages.forEach((m) => {
            if (!window.__chatMsgs.find((x) => x.id === m.id)) {
              window.__chatMsgs.push(m);
              incoming.push(m);
            }
            lastMsgId = Math.max(lastMsgId, m.id);
          });
          if (incoming.length) {
            if (searchQuery) renderOpen();
            else appendMessages(incoming, { animate: true });
            loadConversations(els.search?.value.trim() || '');
          }
        }
        if (res.data.peer) setPeerUi(res.data.peer);
        const typing = res.data.typing || [];
        if (typing.length) {
          els.typing.classList.remove('d-none');
          els.typing.querySelector('span').textContent = typing.map((t) => t.username).join(', ');
        } else {
          els.typing.classList.add('d-none');
        }
        await refreshStatuses();
      } catch {}
    }, window.APP.poll.messages);
  }

  function mobileShowThread() {
    if (window.innerWidth < 992) {
      els.sidebar.classList.add('hidden-mobile');
      els.main.classList.remove('hidden-mobile');
    }
  }
  function mobileShowList() {
    if (window.innerWidth < 992) {
      els.sidebar.classList.remove('hidden-mobile');
      els.main.classList.add('hidden-mobile');
    }
  }

  document.getElementById('backToList')?.addEventListener('click', () => mobileShowList());
  els.search?.addEventListener('input', () => {
    clearTimeout(els.search._t);
    els.search._t = setTimeout(() => loadConversations(els.search.value.trim()), 300);
  });

  els.form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!peerId) return;
    const body = els.input.value.trim();
    if (!body) return;
    const tempId = 'tmp-' + Date.now();
    const optimistic = {
      id: tempId,
      sender_id: window.APP.userId,
      receiver_id: peerId,
      message_type: 'text',
      body,
      delivery_status: 'sent',
      is_deleted: false,
      is_mine: true,
      created_at: new Date().toISOString().slice(0, 19).replace('T', ' '),
      edited_at: null,
      reactions: [],
      reply_to: replyTo ? { id: replyTo, body: els.replyPreview.textContent, type: 'text' } : null,
      sender: { id: window.APP.userId, username: window.APP.username, avatar_url: '' },
      _temp: true,
    };
    window.__chatMsgs.push(optimistic);
    const node = renderMessage(optimistic, { animate: true });
    node.classList.add('pending');
    node.dataset.id = tempId;
    els.scroll.appendChild(node);
    els.scroll.scrollTop = els.scroll.scrollHeight;
    els.input.value = '';
    const savedReply = replyTo;
    replyTo = null;
    els.replyBar.classList.add('d-none');
    try {
      const res = await api('api/messages.php?action=send', {
        method: 'POST',
        body: { receiver_id: peerId, message_type: 'text', body, reply_to_id: savedReply || 0 },
      });
      window.__chatMsgs = window.__chatMsgs.filter((m) => m.id !== tempId);
      window.__chatMsgs.push(res.data);
      lastMsgId = Math.max(lastMsgId, res.data.id);
      const old = els.scroll.querySelector(`.msg[data-id="${tempId}"]`);
      if (old) old.replaceWith(renderMessage(res.data, { animate: false }));
      convFingerprint = '';
      loadConversations();
    } catch (err) {
      const failed = els.scroll.querySelector(`.msg[data-id="${tempId}"]`);
      if (failed) {
        failed.classList.remove('pending');
        failed.classList.add('failed');
      }
      toast(err.message || 'Unable to send message.', 'error');
    }
  });

  els.input?.addEventListener('input', () => {
    if (!peerId) return;
    clearTimeout(typingTimer);
    typingTimer = setTimeout(() => {
      api('api/chat.php?action=typing', { method: 'POST', body: { user_id: peerId } }).catch(() => {});
    }, 300);
  });

  document.getElementById('cancelReply')?.addEventListener('click', () => {
    replyTo = null;
    els.replyBar.classList.add('d-none');
  });

  document.getElementById('btnEmoji')?.addEventListener('click', () => {
    els.emojiPicker.classList.toggle('d-none');
  });
  if (els.emojiPicker) {
    const raw = els.emojiPicker.textContent.trim().split(/\s+/);
    els.emojiPicker.innerHTML = raw.map((em) => `<span style="font-size:1.25rem;padding:0.15rem;cursor:pointer">${em}</span>`).join('');
    els.emojiPicker.addEventListener('click', (e) => {
      if (e.target.tagName === 'SPAN') {
        els.input.value += e.target.textContent;
        els.input.focus();
      }
    });
  }

  document.getElementById('btnSearchMsgs')?.addEventListener('click', () => {
    els.searchBar.classList.toggle('d-none');
    if (!els.searchBar.classList.contains('d-none')) els.msgSearch.focus();
    else { searchQuery = ''; renderOpen(); }
  });
  document.getElementById('closeMsgSearch')?.addEventListener('click', () => {
    els.searchBar.classList.add('d-none');
    searchQuery = '';
    els.msgSearch.value = '';
    renderOpen();
  });
  els.msgSearch?.addEventListener('input', () => {
    searchQuery = els.msgSearch.value.trim();
    renderOpen();
  });

  async function uploadMedia(file, type) {
    if (!peerId || !file) return;
    const fd = new FormData();
    fd.append('receiver_id', peerId);
    fd.append('message_type', type);
    fd.append(type === 'image' ? 'image' : type === 'voice' ? 'voice' : 'file', file);
    if (replyTo) fd.append('reply_to_id', replyTo);
    if (type === 'voice' && file._duration) fd.append('voice_duration', file._duration);
    try {
      showUploadProgress(0);
      const res = await apiUpload('api/messages.php?action=send', fd, showUploadProgress);
      window.__chatMsgs.push(res.data);
      lastMsgId = Math.max(lastMsgId, res.data.id);
      replyTo = null;
      els.replyBar.classList.add('d-none');
      appendMessages([res.data], { animate: true });
      convFingerprint = '';
      loadConversations();
    } catch (err) {
      els.uploadProgress?.classList.add('d-none');
      toast(err.message || 'Unable to send file.', 'error');
    }
  }

  document.getElementById('imageInput')?.addEventListener('change', (e) => {
    uploadMedia(e.target.files?.[0], 'image');
    e.target.value = '';
  });
  document.getElementById('fileInput')?.addEventListener('change', (e) => {
    uploadMedia(e.target.files?.[0], 'file');
    e.target.value = '';
  });

  let recording = false;
  document.getElementById('btnVoiceRec')?.addEventListener('click', async () => {
    if (recording) return;
    try {
      await recorder.start(els.voiceTimer);
      recording = true;
      els.voiceBar.classList.remove('d-none');
    } catch {
      toast('Microphone permission is required for voice messages.', 'error');
    }
  });
  document.getElementById('btnCancelVoice')?.addEventListener('click', () => {
    recorder.cancel();
    recording = false;
    els.voiceBar.classList.add('d-none');
  });
  document.getElementById('btnSendVoice')?.addEventListener('click', async () => {
    const result = await recorder.stop();
    recording = false;
    els.voiceBar.classList.add('d-none');
    if (!result) return;
    const file = new File([result.blob], 'voice.webm', { type: result.blob.type || 'audio/webm' });
    file._duration = result.duration;
    await uploadMedia(file, 'voice');
  });

  document.getElementById('btnVoiceCall')?.addEventListener('click', async () => {
    if (!peerId) return;
    if (peerId === window.APP.userId) return toast('You cannot call yourself.', 'error');
    let stream = null;
    try {
      stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    } catch (err) {
      toast(err?.message || 'Allow microphone to start a call. Check the lock icon in the address bar.', 'error');
      return;
    }
    window.WCCalls?.startCall(peerId, 'voice', window.__peer, stream);
  });
  document.getElementById('btnVideoCall')?.addEventListener('click', async () => {
    if (!peerId) return;
    if (peerId === window.APP.userId) return toast('You cannot call yourself.', 'error');
    let stream = null;
    try {
      stream = await navigator.mediaDevices.getUserMedia({
        audio: true,
        video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
      });
    } catch (err) {
      try {
        stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        toast('Camera unavailable — starting voice call.', 'info');
        window.WCCalls?.startCall(peerId, 'voice', window.__peer, stream);
        return;
      } catch (e2) {
        toast(e2?.message || 'Allow microphone/camera to start a call.', 'error');
        return;
      }
    }
    window.WCCalls?.startCall(peerId, 'video', window.__peer, stream);
  });

  document.getElementById('btnClearChat')?.addEventListener('click', async () => {
    if (!peerId || !confirm('Clear this conversation from your view?')) return;
    await api('api/messages.php?action=clear', { method: 'POST', body: { user_id: peerId } });
    window.__chatMsgs = [];
    renderOpen();
    loadConversations();
    toast('Conversation cleared', 'success');
  });

  document.getElementById('btnBlockPeer')?.addEventListener('click', async () => {
    if (!peerId || !confirm('Block this user?')) return;
    await api('api/blocks.php?action=block', { method: 'POST', body: { user_id: peerId } });
    toast('User blocked', 'success');
    location.href = 'chat.php';
  });

  function openReport(type, id) {
    document.getElementById('reportTargetType').value = type;
    document.getElementById('reportTargetId').value = id;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('reportModal')).show();
  }
  document.getElementById('btnReportPeer')?.addEventListener('click', () => openReport('user', peerId));
  document.getElementById('reportForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    await api('api/forums.php?action=report', {
      method: 'POST',
      body: {
        target_type: document.getElementById('reportTargetType').value,
        target_id: Number(document.getElementById('reportTargetId').value),
        reason: document.getElementById('reportReason').value,
      },
    });
    bootstrap.Modal.getInstance(document.getElementById('reportModal')).hide();
    toast('Report submitted', 'success');
  });

  // Deep-link highlight from notifications: chat.php?user=2&msg=123
  const params = new URLSearchParams(location.search);
  const focusMsg = Number(params.get('msg') || 0);

  async function loadOlder() {
    if (!peerId || loadingOlder || !hasMoreOlder) return;
    const first = (window.__chatMsgs || []).find((m) => typeof m.id === 'number');
    if (!first) return;
    loadingOlder = true;
    const prevHeight = els.scroll.scrollHeight;
    try {
      const res = await api(`api/chat.php?action=history&user_id=${peerId}&before_id=${first.id}`);
      const older = res.data.messages || [];
      hasMoreOlder = !!res.data.has_more && older.length > 0;
      if (older.length) {
        window.__chatMsgs = [...older, ...window.__chatMsgs];
        const frag = document.createDocumentFragment();
        older.forEach((m) => frag.appendChild(renderMessage(m, { animate: false })));
        els.scroll.prepend(frag);
        els.scroll.scrollTop = els.scroll.scrollHeight - prevHeight;
      }
    } catch {}
    loadingOlder = false;
  }

  els.scroll?.addEventListener('scroll', () => {
    if (els.scroll.scrollTop < 60) loadOlder();
  });

  document.getElementById('btnMarkAllRead')?.addEventListener('click', async () => {
    try {
      await api('api/chat.php?action=mark_all_read', { method: 'POST', body: {} });
      convFingerprint = '';
      await loadConversations();
      toast('All marked as read', 'success');
    } catch (e) { toast(e.message, 'error'); }
  });

  document.getElementById('lightboxClose')?.addEventListener('click', () => {
    document.getElementById('lightbox')?.classList.add('d-none');
  });
  document.getElementById('lightbox')?.addEventListener('click', (e) => {
    if (e.target.id === 'lightbox') e.currentTarget.classList.add('d-none');
  });

  loadConversations().catch(() => {});
  if (peerId) {
    openChat(peerId).then(() => {
      if (focusMsg) {
        const el = els.scroll.querySelector(`.msg[data-id="${focusMsg}"]`);
        if (el) {
          el.classList.add('highlight-hit');
          el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      }
    }).catch((e) => toast(e.message, 'error'));
  } else if (window.innerWidth < 992) {
    mobileShowList();
  }
})();
