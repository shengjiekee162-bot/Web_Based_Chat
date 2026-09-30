(() => {
  const { api, toast } = window.WC;
  const form = document.getElementById('settingsForm');
  if (!form) return;
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const msg = document.getElementById('settingsMsg');
    try {
      await api('api/profile.php?action=settings', {
        method: 'POST',
        body: {
          notify_desktop: document.getElementById('notifyDesktop').checked ? 1 : 0,
          show_last_seen: document.getElementById('showLastSeen').checked ? 1 : 0,
          prefer_call_type: document.getElementById('preferCallType').value,
        },
      });
      msg.className = 'small mt-2 text-success';
      msg.textContent = 'Settings saved.';
      localStorage.setItem('wc_notify_desktop', document.getElementById('notifyDesktop').checked ? '1' : '0');
      localStorage.setItem('wc_prefer_call', document.getElementById('preferCallType').value);
    } catch (err) {
      msg.className = 'small mt-2 text-danger';
      msg.textContent = err.message;
    }
  });
})();
