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
    }
  };

  window.addEventListener('online', () => { updateConnection(); updateQueueStatus(); });
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
})();
