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

  async function updateDashboardSummary() {
    const metricNodes = document.querySelectorAll('[data-summary-module][data-summary-metric]');
    if (metricNodes.length === 0) return;

    const statusNodes = document.querySelectorAll('[data-summary-status]');
    const updatedNode = document.querySelector('[data-summary-updated]');

    if (!navigator.onLine) {
      statusNodes.forEach(node => node.textContent = 'Offline');
      if (updatedNode) updatedNode.textContent = 'Offline';
      return;
    }

    try {
      const response = await fetch('/api/v1/dashboard-summary.php', {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' },
        cache: 'no-store'
      });
      if (!response.ok) throw new Error('summary request failed');
      const payload = await response.json();
      const modules = payload.modules || {};

      metricNodes.forEach(node => {
        const moduleKey = node.dataset.summaryModule;
        const metricKey = node.dataset.summaryMetric;
        const result = modules[moduleKey];

        if (result && result.status === 'connected' && result.metrics && metricKey in result.metrics) {
          node.textContent = String(result.metrics[metricKey]);
        } else {
          node.textContent = '—';
        }
      });

      statusNodes.forEach(node => {
        const result = modules[node.dataset.summaryStatus];
        if (!result) {
          node.textContent = 'Not connected';
          return;
        }
        const labels = {
          connected: 'Live data',
          needs_mapping: 'Map this project',
          unauthorized: 'Check integration key',
          not_configured: 'Enable module integration',
          unavailable: 'Temporarily unavailable'
        };
        node.textContent = labels[result.status] || 'Not connected';
      });

      if (updatedNode) {
        updatedNode.textContent = 'Updated just now';
      }
    } catch (_) {
      statusNodes.forEach(node => node.textContent = 'Summary unavailable');
      if (updatedNode) updatedNode.textContent = 'Unable to refresh';
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

  window.addEventListener('online', () => { updateConnection(); updateQueueStatus(); updateModuleHealth(); updateDashboardSummary(); });
  window.addEventListener('offline', () => { updateConnection(); updateQueueStatus(); });
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      updateConnection();
      updateQueueStatus();
      updateModuleHealth();
      updateDashboardSummary();
    }
  });

  if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', async () => {
      try {
        const registration = await navigator.serviceWorker.register('/service-worker.js', {
          updateViaCache: 'none'
        });
        await registration.update();
      } catch (_) {}
    });

    navigator.serviceWorker.addEventListener('controllerchange', () => {
      const key = 'suite-sw-refresh-v0.3.1';
      if (sessionStorage.getItem(key)) return;
      sessionStorage.setItem(key, '1');
      window.location.reload();
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
  updateDashboardSummary();
})();
