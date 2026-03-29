(() => {
  const body = document.body;
  const toggle = document.querySelector('[data-nav-toggle]');
  const menu = document.querySelector('[data-mobile-menu]');
  const backdrop = document.querySelector('[data-mobile-backdrop]');
  const closeButton = document.querySelector('[data-nav-close]');
  let previousFocus = null;

  const focusableSelector = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])'
  ].join(',');

  function focusableElements() {
    return menu ? Array.from(menu.querySelectorAll(focusableSelector)) : [];
  }

  function openMenu() {
    if (!toggle || !menu || !backdrop) return;
    previousFocus = document.activeElement;
    toggle.setAttribute('aria-expanded', 'true');
    menu.setAttribute('aria-hidden', 'false');
    menu.removeAttribute('inert');
    menu.classList.add('is-open');
    backdrop.hidden = false;
    requestAnimationFrame(() => backdrop.classList.add('is-visible'));
    body.classList.add('nav-open');
    const first = focusableElements()[0];
    if (first) first.focus();
  }

  function closeMenu() {
    if (!toggle || !menu || !backdrop) return;
    toggle.setAttribute('aria-expanded', 'false');
    menu.setAttribute('aria-hidden', 'true');
    menu.setAttribute('inert', '');
    menu.classList.remove('is-open');
    backdrop.classList.remove('is-visible');
    body.classList.remove('nav-open');
    window.setTimeout(() => {
      backdrop.hidden = true;
    }, 180);
    if (previousFocus instanceof HTMLElement) {
      previousFocus.focus();
    } else {
      toggle.focus();
    }
  }

  if (toggle && menu && backdrop) {
    toggle.addEventListener('click', () => {
      const expanded = toggle.getAttribute('aria-expanded') === 'true';
      if (expanded) {
        closeMenu();
      } else {
        openMenu();
      }
    });

    closeButton?.addEventListener('click', closeMenu);
    backdrop.addEventListener('click', closeMenu);
    menu.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeMenu));

    document.addEventListener('keydown', (event) => {
      if (toggle.getAttribute('aria-expanded') !== 'true') return;

      if (event.key === 'Escape') {
        closeMenu();
        return;
      }

      if (event.key !== 'Tab') return;
      const elements = focusableElements();
      if (!elements.length) return;
      const first = elements[0];
      const last = elements[elements.length - 1];

      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    });

    window.addEventListener('resize', () => {
      if (window.innerWidth > 960 && toggle.getAttribute('aria-expanded') === 'true') {
        closeMenu();
      }
    });
  }

  document.querySelectorAll('[data-async-form]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      if (!window.fetch || !window.FormData) return;

      event.preventDefault();
      const status = form.querySelector('[data-form-status]');
      const button = form.querySelector('[data-submit-button]');
      const errors = form.querySelectorAll('[data-field-error]');
      errors.forEach((node) => {
        node.textContent = '';
      });

      if (status) {
        status.classList.remove('is-success', 'is-error');
        status.textContent = 'Sending request...';
      }

      if (button) {
        button.disabled = true;
        button.dataset.originalLabel = button.textContent || '';
        button.textContent = 'Sending...';
      }

      try {
        const response = await fetch(form.action, {
          method: form.method || 'POST',
          body: new FormData(form),
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'fetch'
          }
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
          if (data.errors) {
            Object.entries(data.errors).forEach(([field, message]) => {
              const errorNode = form.querySelector(`[data-field-error="${field}"]`);
              if (errorNode) {
                errorNode.textContent = String(message);
              }
            });
          }
          if (status) {
            status.classList.add('is-error');
            status.textContent = data.message || 'Unable to send request right now.';
          }
          const firstError = form.querySelector('.field-error:not(:empty)');
          if (firstError) {
            const field = firstError.previousElementSibling;
            if (field instanceof HTMLElement) {
              field.focus();
            }
          }
          return;
        }

        form.reset();
        if (status) {
          status.classList.add('is-success');
          status.textContent = data.message || 'Request received.';
        }
      } catch (error) {
        if (status) {
          status.classList.add('is-error');
          status.textContent = 'A network issue interrupted the request. Please try again.';
        }
      } finally {
        if (button) {
          button.disabled = false;
          button.textContent = button.dataset.originalLabel || 'Submit';
        }
      }
    });
  });
})();
