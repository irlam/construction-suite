'use strict';
// The fragment stays out of request URLs and is cleared before previewing.
const form = document.getElementById('invitation-preview');
if (form && location.hash) {
  const token = new URLSearchParams(location.hash.slice(1)).get('token');
  history.replaceState(null, '', location.pathname);
  if (/^[a-f0-9]{64}$/.test(token || '')) {
    document.getElementById('invitation-token').value = token;
    form.requestSubmit();
  }
}
