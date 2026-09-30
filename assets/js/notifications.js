(() => {
  const { api, escapeHtml, timeLabel } = window.WC;

  async function refreshBadge() {
    try {
      const res = await api('api/notifications.php?action=unread_count');
      const badge = document.getElementById('navNotifBadge');
      if (!badge) return;
      const n = res.data.unread || 0;
      const prev = Number(badge.textContent || 0);
      badge.textContent = n;
      badge.classList.toggle('d-none', n <= 0);
      if (n > prev && window.WC?.notifyDesktop) {
        window.WC.notifyDesktop('WebConnect', 'You have new notifications', 'notifications.php');
      }
    } catch {}
  }

  async function loadPage() {
    if (!window.NOTIF_PAGE) return;
    const res = await api('api/notifications.php?action=list');
    const el = document.getElementById('notifList');
    el.innerHTML = res.data.items.length ? res.data.items.map((n) => `
      <a class="list-row ${n.is_read == 0 ? 'border-primary' : ''}" href="${escapeHtml(n.link || '#')}" data-id="${n.id}">
        <div class="meta">
          <strong>${escapeHtml(n.title)}</strong>
          <small>${escapeHtml(n.body || '')} · ${timeLabel(n.created_at)}</small>
        </div>
      </a>
    `).join('') : '<div class="text-muted">No notifications.</div>';
    el.querySelectorAll('[data-id]').forEach((a) => a.addEventListener('click', () => {
      api('api/notifications.php?action=mark_read', { method: 'POST', body: { id: Number(a.dataset.id) } }).catch(() => {});
    }));
  }

  document.getElementById('markAllRead')?.addEventListener('click', async () => {
    await api('api/notifications.php?action=mark_read', { method: 'POST', body: { id: 0 } });
    loadPage();
    refreshBadge();
  });

  refreshBadge();
  loadPage();
  setInterval(refreshBadge, window.APP?.poll?.notif || 5000);
})();
