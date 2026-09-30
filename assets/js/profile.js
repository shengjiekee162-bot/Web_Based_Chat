(() => {
  const { api, toast } = window.WC;
  if (!document.getElementById('profileForm')) return;

  document.getElementById('profileForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const msg = document.getElementById('profileMsg');
    try {
      await api('api/profile.php?action=update', {
        method: 'POST',
        body: {
          username: fd.get('username'),
          email: fd.get('email'),
          status_message: fd.get('status_message'),
        },
      });
      msg.className = 'mt-2 small text-success';
      msg.textContent = 'Profile saved.';
    } catch (err) {
      msg.className = 'mt-2 small text-danger';
      msg.textContent = err.message;
    }
  });

  document.getElementById('passwordForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const msg = document.getElementById('passwordMsg');
    try {
      await api('api/profile.php?action=password', {
        method: 'POST',
        body: {
          current_password: fd.get('current_password'),
          new_password: fd.get('new_password'),
          confirm_password: fd.get('confirm_password'),
        },
      });
      msg.className = 'mt-2 small text-success';
      msg.textContent = 'Password updated.';
      e.target.reset();
    } catch (err) {
      msg.className = 'mt-2 small text-danger';
      msg.textContent = err.message;
    }
  });

  document.getElementById('avatarInput').addEventListener('change', async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    const fd = new FormData();
    fd.append('avatar', file);
    try {
      const res = await api('api/profile.php?action=avatar', { method: 'POST', body: fd });
      document.getElementById('profileAvatarPreview').src = res.data.avatar_url + '&t=' + Date.now();
      toast('Avatar updated', 'success');
    } catch (err) {
      toast(err.message, 'error');
    }
  });
})();
