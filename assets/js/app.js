(() => {
  const APP = window.APP || {};

  /** Prefer live page origin when config still points at 127.0.0.1 (common cPanel misconfig). */
  function appBase() {
    const configured = String(APP.url || '').replace(/\/$/, '');
    try {
      const cfg = new URL(configured || window.location.href, window.location.origin);
      const pageHost = window.location.host;
      if (cfg.host !== pageHost && /^(127\.0\.0\.1|localhost)(:\d+)?$/i.test(cfg.host)) {
        const parts = window.location.pathname.split('/').filter(Boolean);
        // Drop the current script name (e.g. chat.php)
        if (parts.length && /\.php$/i.test(parts[parts.length - 1])) {
          parts.pop();
        }
        return window.location.origin + (parts.length ? '/' + parts.join('/') : '');
      }
      return configured || (window.location.origin + window.location.pathname.replace(/\/[^/]*$/, ''));
    } catch {
      return configured || window.location.origin;
    }
  }

  async function api(path, options = {}) {
    const opts = { ...options };
    opts.headers = opts.headers || {};
    if (!(opts.body instanceof FormData)) {
      opts.headers['Content-Type'] = opts.headers['Content-Type'] || 'application/json';
      opts.headers['X-CSRF-TOKEN'] = APP.csrf;
      if (opts.body && typeof opts.body === 'object') {
        opts.body = JSON.stringify(opts.body);
      }
    } else {
      opts.headers['X-CSRF-TOKEN'] = APP.csrf;
      if (!opts.body.has('csrf_token')) opts.body.append('csrf_token', APP.csrf);
    }
    const res = await fetch(appBase() + '/' + path.replace(/^\//, ''), {
      credentials: 'same-origin',
      ...opts,
    });
    const raw = await res.text();
    let data;
    try {
      data = raw ? JSON.parse(raw) : {};
    } catch {
      const snippet = raw.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 140);
      throw new Error(snippet || `Server error (${res.status})`);
    }
    if (!res.ok || data.success === false) {
      const err = new Error(data.message || `Request failed (${res.status})`);
      err.status = res.status;
      err.data = data;
      throw err;
    }
    return data;
  }

  function apiUpload(path, formData, onProgress) {
    return new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest();
      xhr.open('POST', appBase() + '/' + path.replace(/^\//, ''));
      xhr.withCredentials = true;
      xhr.setRequestHeader('X-CSRF-TOKEN', APP.csrf);
      if (!formData.has('csrf_token')) formData.append('csrf_token', APP.csrf);
      xhr.upload.onprogress = (e) => {
        if (e.lengthComputable && typeof onProgress === 'function') {
          onProgress(Math.round((e.loaded / e.total) * 100));
        }
      };
      xhr.onload = () => {
        try {
          const data = JSON.parse(xhr.responseText || '{}');
          if (xhr.status >= 200 && xhr.status < 300 && data.success !== false) resolve(data);
          else reject(new Error(data.message || `Upload failed (${xhr.status})`));
        } catch {
          reject(new Error('Invalid server response during upload'));
        }
      };
      xhr.onerror = () => reject(new Error('Network error during upload'));
      xhr.send(formData);
    });
  }

  function escapeHtml(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function timeLabel(iso) {
    if (!iso) return '';
    const d = new Date(iso.replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return iso;
    return d.toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
  }

  function presenceClass(p) {
    return ['online', 'away', 'busy'].includes(p) ? p : 'offline';
  }

  function toast(msg, type = 'info') {
    let el = document.getElementById('appToast');
    if (!el) {
      el = document.createElement('div');
      el.id = 'appToast';
      el.style.cssText = 'position:fixed;bottom:1rem;right:1rem;z-index:3000;max-width:340px';
      document.body.appendChild(el);
    }
    const alert = document.createElement('div');
    alert.className = `alert alert-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'secondary'} shadow mb-2`;
    alert.textContent = msg;
    el.appendChild(alert);
    setTimeout(() => alert.remove(), 4200);
  }

  function ensureDesktopPermission() {
    if (!('Notification' in window)) return;
    if (Notification.permission === 'default') {
      Notification.requestPermission().catch(() => {});
    }
  }

  function notifyDesktop(title, body, link) {
    if (localStorage.getItem('wc_notify_desktop') === '0') return;
    if (!('Notification' in window) || Notification.permission !== 'granted') return;
    if (document.hasFocus()) return;
    try {
      const n = new Notification(title, { body: body || '', icon: appBase() + '/assets/images/default-avatar.svg' });
      n.onclick = () => {
        window.focus();
        if (link) location.href = link.startsWith('http') ? link : (appBase() + '/' + link.replace(/^\//, ''));
        n.close();
      };
    } catch {}
  }

  // Theme
  const themeBtn = document.getElementById('themeToggle');
  function applyThemeIcon() {
    const t = document.documentElement.getAttribute('data-theme') || 'light';
    if (themeBtn) {
      themeBtn.innerHTML = t === 'dark'
        ? '<i class="fa-solid fa-sun"></i> Light mode'
        : '<i class="fa-solid fa-moon"></i> Dark mode';
    }
  }
  applyThemeIcon();
  themeBtn?.addEventListener('click', () => {
    const next = (document.documentElement.getAttribute('data-theme') || 'light') === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('wc_theme', next);
    applyThemeIcon();
  });

  // Sidebar toggle (mobile)
  const sidebar = document.getElementById('appSidebar');
  const toggle = document.getElementById('sidebarToggle');
  if (toggle && sidebar) {
    toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
    document.addEventListener('click', (e) => {
      if (window.innerWidth < 992 && sidebar.classList.contains('open') && !sidebar.contains(e.target) && e.target !== toggle && !toggle.contains(e.target)) {
        sidebar.classList.remove('open');
      }
    });
  }

  // Presence heartbeat
  setInterval(() => {
    api('api/presence.php?action=heartbeat', { method: 'POST', body: { presence: 'online' } }).catch(() => {});
  }, 30000);
  api('api/presence.php?action=heartbeat', { method: 'POST', body: { presence: 'online' } }).catch(() => {});
  ensureDesktopPermission();

  function createRecorder({ onReady }) {
    let mediaRecorder = null;
    let chunks = [];
    let stream = null;
    let startedAt = 0;
    let timer = null;

    async function start(timerEl) {
      stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      chunks = [];
      const mime = MediaRecorder.isTypeSupported('audio/webm') ? 'audio/webm' : '';
      mediaRecorder = new MediaRecorder(stream, mime ? { mimeType: mime } : undefined);
      mediaRecorder.ondataavailable = (e) => { if (e.data.size) chunks.push(e.data); };
      mediaRecorder.start(200);
      startedAt = Date.now();
      if (timerEl) {
        timer = setInterval(() => {
          const s = Math.floor((Date.now() - startedAt) / 1000);
          timerEl.textContent = `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
        }, 250);
      }
      onReady && onReady();
    }

    function stop() {
      return new Promise((resolve) => {
        if (!mediaRecorder) return resolve(null);
        mediaRecorder.onstop = () => {
          clearInterval(timer);
          stream?.getTracks().forEach((t) => t.stop());
          const blob = new Blob(chunks, { type: mediaRecorder.mimeType || 'audio/webm' });
          const duration = (Date.now() - startedAt) / 1000;
          resolve({ blob, duration });
        };
        mediaRecorder.stop();
      });
    }

    function cancel() {
      clearInterval(timer);
      try { mediaRecorder?.stop(); } catch {}
      stream?.getTracks().forEach((t) => t.stop());
      mediaRecorder = null;
      chunks = [];
    }

    return { start, stop, cancel };
  }

  window.WC = { api, apiUpload, escapeHtml, timeLabel, presenceClass, toast, createRecorder, notifyDesktop, appBase };
  // Keep APP.url in sync for older callers (chat export links, etc.)
  APP.url = appBase();
})();
