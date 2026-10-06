'use strict';
for (const input of document.querySelectorAll('[data-invitation-link]')) {
  input.value = new URL(input.value, location.origin).href;
  input.addEventListener('click', () => input.select());
}
