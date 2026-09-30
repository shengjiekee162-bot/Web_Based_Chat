(() => {
  const { api, escapeHtml, timeLabel, toast } = window.WC;
  const el = document.getElementById('callHistoryList');
  if (!el) return;
  api('api/calls.php?action=history').then((res) => {
    if (!res.data.length) {
      el.innerHTML = '<div class="text-muted">No calls yet.</div>';
      return;
    }
    el.innerHTML = res.data.map((c) => `
      <div class="list-row">
        <div class="meta">
          <strong>${escapeHtml(c.peer_name)}</strong>
          <small>${escapeHtml(c.direction)} ${escapeHtml(c.call_type)} · ${escapeHtml(c.status)} · ${timeLabel(c.created_at)}</small>
        </div>
        <span class="badge text-bg-secondary">${escapeHtml(c.status)}</span>
      </div>
    `).join('');
  }).catch((e) => {
    el.innerHTML = '';
    toast(e.message, 'error');
  });
})();
