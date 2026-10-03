(() => {
  'use strict';

  const dots = document.querySelectorAll('[data-connection-dot]');
  const labels = document.querySelectorAll('[data-connection-text]');
  const syncLabels = document.querySelectorAll('[data-sync-label]');
  let deferredPrompt = null;

  function updateConnection() {
    const online = navigator.onLine;
    dots.forEach(dot => {
      dot.classList.toggle('online', online);
      dot.classList.toggle('offline', !online);
    });
    labels.forEach(label => label.textContent = online ? 'Online' : 'Offline');
    if (!online) syncLabels.forEach(label => label.textContent = 'Paused offline');
    else if ((Number(localStorage.getItem('suiteQueued')) || 0) === 0) {
      syncLabels.forEach(label => label.textContent = 'Up to date');
    }
  }

  function saveContext() {
    if (!document.body.dataset.suiteProject) return;
    localStorage.setItem('suiteContext', JSON.stringify({
      user: document.body.dataset.suiteUser || '',
      project: document.body.dataset.suiteProject || '',
      organisation: document.body.dataset.suiteOrganisation || '',
      savedAt: Date.now()
    }));
  }

  async function updateModuleHealth() {
    const summary = document.querySelector('[data-module-health-summary]');
    const badges = document.querySelectorAll('[data-module-health]');
    if (!summary && badges.length === 0) return;

    if (!navigator.onLine) {
      if (summary) summary.textContent = 'Offline';
      badges.forEach(badge => {
        badge.className = 'module-health unknown';
        badge.innerHTML = '<span></span>Not checked';
      });
      return;
    }

    try {
      const response = await fetch('/api/v1/module-health.php', {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' },
        cache: 'no-store'
      });
      if (!response.ok) throw new Error('health request failed');
      const payload = await response.json();
      const modules = new Map((payload.modules || []).map(item => [item.key, item]));

      badges.forEach(badge => {
        const item = modules.get(badge.dataset.moduleHealth);
        const available = item && item.status === 'available';
        badge.className = 'module-health ' + (available ? 'available' : 'unavailable');
        badge.innerHTML = '<span></span>' + (available ? 'Available' : 'Unavailable');
        if (item && item.latency_ms !== null) {
          badge.title = String(item.http_code || '') + ' · ' + String(item.latency_ms) + ' ms';
        }
      });

      if (summary && payload.summary) {
        summary.textContent = String(payload.summary.available) + '/' + String(payload.summary.total) + ' available';
      }
    } catch (_) {
      if (summary) summary.textContent = 'Check unavailable';
      badges.forEach(badge => {
        badge.className = 'module-health unknown';
        badge.innerHTML = '<span></span>Unknown';
      });
    }
  }

  function updateQueueStatus() {
    const queued = Number(localStorage.getItem('suiteQueued')) || 0;
    syncLabels.forEach(label => {
      if (!navigator.onLine) label.textContent = queued ? String(queued) + ' queued' : 'Paused offline';
      else label.textContent = queued ? String(queued) + ' queued' : 'Up to date';
    });
  }

  window.SuiteSyncStatus = {
    setQueued(count) {
      localStorage.setItem('suiteQueued', String(Math.max(0, Number(count) || 0)));
      updateQueueStatus();
      updateModuleHealth();
    }
  };

  window.addEventListener('online', () => { updateConnection(); updateQueueStatus(); updateModuleHealth(); });
  window.addEventListener('offline', () => { updateConnection(); updateQueueStatus(); });
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      updateConnection();
      updateQueueStatus();
    }
  });

  if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/service-worker.js').catch(() => {});
    });
  }

  window.addEventListener('beforeinstallprompt', event => {
    event.preventDefault();
    deferredPrompt = event;
    document.querySelectorAll('[data-install]').forEach(button => button.hidden = false);
  });

  document.querySelectorAll('[data-install]').forEach(button => {
    button.addEventListener('click', async () => {
      if (deferredPrompt) {
        deferredPrompt.prompt();
        await deferredPrompt.userChoice;
        deferredPrompt = null;
        button.hidden = true;
        return;
      }
      const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
      const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone;
      const hint = document.querySelector('[data-ios-install]');
      if (isIos && !standalone && hint) hint.hidden = false;
    });
  });

  document.querySelector('[data-ios-close]')?.addEventListener('click', () => {
    const hint = document.querySelector('[data-ios-install]');
    if (hint) hint.hidden = true;
  });

  saveContext();
  updateConnection();
  updateQueueStatus();
  updateModuleHealth();
})();
