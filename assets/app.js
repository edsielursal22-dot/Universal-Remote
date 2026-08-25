(() => {
  'use strict';

  const state = {
    activeDeviceId: window.REMOTE_APP?.activeDeviceId ?? null,
    volume: 24,
    channel: 7,
    toastTimer: null,
  };

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const devices = () => window.REMOTE_APP?.devices ?? [];
  const activeDevice = () => devices().find((device) => Number(device.device_id) === Number(state.activeDeviceId));

  function updateLocalDateTime() {
    const now = new Date();
    const hour = now.getHours();
    const period = hour < 12 ? 'morning' : hour < 17 ? 'afternoon' : 'evening';
    const date = new Intl.DateTimeFormat(undefined, { weekday: 'long', month: 'long', day: 'numeric' }).format(now);
    const dateElement = $('#currentDate');
    const greetingElement = $('#greetingPeriod');
    if (dateElement) dateElement.textContent = date;
    if (greetingElement) greetingElement.textContent = period;
  }

  function showToast(message, isError = false) {
    const toast = $('#toast');
    if (!toast) return;
    $('#toastMessage').textContent = message;
    toast.style.borderColor = isError ? 'rgba(255,121,121,.3)' : '';
    toast.style.background = isError ? '#2a1818' : '';
    toast.style.color = isError ? '#ffb3ad' : '';
    toast.classList.add('show');
    window.clearTimeout(state.toastTimer);
    state.toastTimer = window.setTimeout(() => toast.classList.remove('show'), 2800);
  }

  function openModal(id) {
    if (id === 'settingsModal') closeModal('profileModal');
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
    const focusable = $('input, select, button', modal);
    focusable?.focus();
  }

  function closeModal(modal) {
    const element = typeof modal === 'string' ? document.getElementById(modal) : modal;
    if (!element) return;
    element.hidden = true;
    if (!$$('.modal-backdrop:not([hidden])').length) document.body.style.overflow = '';
  }

  function setActiveDevice(id) {
    const device = devices().find((item) => Number(item.device_id) === Number(id));
    if (!device) return;
    state.activeDeviceId = Number(device.device_id);
    $$('.device-card').forEach((card) => card.classList.toggle('selected', Number(card.dataset.deviceId) === state.activeDeviceId));
    const name = $('#activeDeviceName');
    const meta = $('#activeDeviceMeta');
    const emblem = $('#deviceEmblem');
    if (name) name.textContent = device.device_name;
    if (meta) meta.textContent = `${device.brand} · ${device.device_type}`;
    if (emblem) emblem.textContent = device.device_type.toLowerCase().includes('audio') ? 'A' : device.device_type.toLowerCase().includes('projector') ? 'P' : 'TV';
    renderApps(device);
    $('#commandFeedback').textContent = device.connection_status === 'offline' ? 'This device is offline. Commands will be sent when it reconnects.' : 'Select a control to send a command.';
  }

  function renderApps(device) {
    const row = $('.quick-row');
    if (!row) return;
    const shortcuts = device.apps ?? [];
    row.innerHTML = `<button class="quick-chip active" type="button" data-app-filter="all">All apps</button>` + shortcuts.map((app) => {
      return `<button class="quick-chip" type="button" data-app-filter="${escapeHtml(String(app.app_name).toLowerCase())}">${escapeHtml(app.app_name)}</button>`;
    }).join('') + '<button class="quick-chip" type="button" data-open="add-device"><span aria-hidden="true">＋</span>Add shortcut</button>';
    bindAppChips();
  }

  function escapeHtml(value) {
    return value.replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character]));
  }

  async function api(payload) {
    const response = await fetch('backend/api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.REMOTE_APP.csrfToken },
      body: JSON.stringify(payload),
    });
    const result = await response.json().catch(() => ({ ok: false, message: 'The server returned an invalid response.' }));
    if (!response.ok || !result.ok) throw new Error(result.message || 'Request failed.');
    return result.data;
  }

  async function sendCommand(command) {
    if (!state.activeDeviceId) {
      showToast('Add a device before sending commands.', true);
      return;
    }
    if (activeDevice()?.connection_status === 'offline') {
      const message = 'This device is disconnected. Connect it before sending commands.';
      $('#commandFeedback').textContent = message;
      showToast(message, true);
      return;
    }
    const feedback = $('#commandFeedback');
    feedback.textContent = 'Sending command…';
    try {
      const result = await api({ action: 'command', deviceId: state.activeDeviceId, command });
      feedback.textContent = result.message;
      if (command === 'volume-up') state.volume = Math.min(100, state.volume + 1);
      if (command === 'volume-down') state.volume = Math.max(0, state.volume - 1);
      if (command === 'channel-up') state.channel = Math.min(999, state.channel + 1);
      if (command === 'channel-down') state.channel = Math.max(1, state.channel - 1);
      $('#volumeValue').textContent = String(state.volume);
      $('#channelValue').textContent = String(state.channel).padStart(2, '0');
      addActivity(result.message, activeDevice()?.device_name || 'Active device');
    } catch (error) {
      feedback.textContent = error.message;
      showToast(error.message, true);
    }
  }

  function addActivity(action, deviceName) {
    const list = $('#activityList');
    if (!list) return;
    const empty = $('.empty-activity', list);
    empty?.remove();
    const row = document.createElement('div');
    row.className = 'activity-row';
    row.innerHTML = `<div class="activity-mark" aria-hidden="true">⌁</div><div class="activity-copy"><strong>${escapeHtml(action)}</strong><span>${escapeHtml(deviceName)} · Just now</span></div>`;
    list.prepend(row);
    while ($$('.activity-row', list).length > 8) list.lastElementChild.remove();
  }

  function bindAppChips() {
    $$('[data-app-filter]').forEach((chip) => {
      chip.addEventListener('click', () => {
        $$('[data-app-filter]').forEach((item) => item.classList.remove('active'));
        chip.classList.add('active');
        const name = chip.dataset.appFilter;
        if (name !== 'all') {
          $('#commandFeedback').textContent = `${chip.textContent.trim()} shortcut selected.`;
          showToast(`${chip.textContent.trim()} shortcut selected.`);
        }
      });
    });
  }

  function bindNavigation() {
    $$('[data-view]').forEach((button) => button.addEventListener('click', () => {
      const target = button.dataset.view;
      const element = document.getElementById(`${target}View`);
      element?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      $$('[data-view]').forEach((item) => item.classList.toggle('active', item.dataset.view === target));
    }));
  }

  function bindSearch() {
    $('#searchInput')?.addEventListener('input', (event) => {
      const query = event.target.value.trim().toLowerCase();
      let visible = 0;
      $$('.device-card').forEach((card) => {
        const matches = !query || card.dataset.deviceName.includes(query);
        card.hidden = !matches;
        if (matches) visible += 1;
      });
      $('#deviceCount').textContent = query ? `${visible} result${visible === 1 ? '' : 's'}` : `${devices().length} connected`;
    });
  }

  function bindCustomSelects() {
    $$('[data-custom-select]').forEach((select) => {
      const trigger = $('[data-select-trigger]', select);
      const menu = $('.select-menu', select);
      const valueInput = $('[data-select-value]', select);
      const label = $('[data-select-label]', select);
      if (!trigger || !menu || !valueInput || !label) return;

      const close = () => {
        menu.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
      };
      const open = () => {
        $$('[data-custom-select]').forEach((other) => {
          if (other !== select) $('[data-select-trigger]', other)?.setAttribute('aria-expanded', 'false');
          if (other !== select) { const otherMenu = $('.select-menu', other); if (otherMenu) otherMenu.hidden = true; }
        });
        menu.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
      };

      trigger.addEventListener('click', () => (menu.hidden ? open() : close()));
      $$('[data-select-option]', menu).forEach((option) => option.addEventListener('click', () => {
        valueInput.value = option.dataset.value ?? '';
        label.textContent = option.textContent.trim();
        $$('[data-select-option]', menu).forEach((item) => item.setAttribute('aria-selected', String(item === option)));
        close();
      }));
      trigger.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); menu.hidden ? open() : close(); }
        if (event.key === 'Escape') close();
      });
    });
    document.addEventListener('click', (event) => {
      if (!event.target.closest('[data-custom-select]')) {
        $$('[data-custom-select]').forEach((select) => {
          const menu = $('.select-menu', select);
          const trigger = $('[data-select-trigger]', select);
          if (menu) menu.hidden = true;
          trigger?.setAttribute('aria-expanded', 'false');
        });
      }
    });
  }

  function bindModals() {
    $$('[data-open]').forEach((button) => button.addEventListener('click', () => {
      const target = button.dataset.open === 'add-device' ? 'addDeviceModal' : button.dataset.open === 'profile' ? 'profileModal' : 'settingsModal';
      openModal(target);
    }));
    $$('[data-close-modal]').forEach((button) => button.addEventListener('click', () => closeModal(button.closest('.modal-backdrop'))));
    $$('.modal-backdrop').forEach((backdrop) => backdrop.addEventListener('click', (event) => {
      if (event.target === backdrop) closeModal(backdrop);
    }));
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') $$('.modal-backdrop:not([hidden])').forEach(closeModal);
    });
  }

  function bindDeviceCards() {
    $$('.device-card').forEach((card) => card.addEventListener('click', () => setActiveDevice(card.dataset.deviceId)));
  }

  async function toggleConnection(button) {
    const deviceId = Number(button.dataset.deviceId);
    const deviceName = button.dataset.deviceLabel || 'this device';
    const action = button.dataset.connectionAction;
    if (action === 'disconnect' && !window.confirm(`Disconnect ${deviceName}?`)) return;
    button.disabled = true;
    button.textContent = action === 'connect' ? 'Connecting…' : 'Disconnecting…';
    try {
      const result = await api({ action, deviceId });
      const device = devices().find((item) => Number(item.device_id) === deviceId);
      const connected = action === 'connect';
      if (device) device.connection_status = connected ? 'connected' : 'offline';
      const row = button.closest('.device-row');
      const status = $('.device-status', row);
      if (status) {
        status.classList.toggle('offline', !connected);
        status.innerHTML = `<span class="status-dot" aria-hidden="true"></span>${connected ? 'Connected' : 'Offline'}`;
      }
      button.dataset.connectionAction = connected ? 'disconnect' : 'connect';
      button.classList.toggle('connect', connected);
      button.classList.toggle('disconnect', !connected);
      button.textContent = connected ? 'Disconnect' : 'Connect';
      button.disabled = false;
      const connectedCount = devices().filter((item) => item.connection_status !== 'offline').length;
      $('#deviceCount').textContent = `${connectedCount} connected`;
      if (state.activeDeviceId === deviceId) {
        $('#commandFeedback').textContent = connected ? 'Device connected. Select a control to send a command.' : 'This device is disconnected. Connect it before sending commands.';
      }
      addActivity(result.message, deviceName);
      showToast(`${deviceName} ${connected ? 'connected' : 'disconnected'}.`);
    } catch (error) {
      button.disabled = false;
      button.textContent = action === 'connect' ? 'Connect' : 'Disconnect';
      showToast(error.message, true);
    }
  }

  function bindConnectionButtons() {
    $$('[data-connection-action]').forEach((button) => button.addEventListener('click', (event) => {
      event.stopPropagation();
      toggleConnection(button);
    }));
  }

  async function removeDevice(button) {
    const deviceId = Number(button.dataset.removeDevice);
    const deviceName = button.dataset.deviceLabel || 'this device';
    if (!window.confirm(`Remove ${deviceName} permanently?`)) return;
    button.disabled = true;
    button.textContent = 'Removing…';
    try {
      await api({ action: 'remove-device', deviceId });
      const removedWasActive = Number(state.activeDeviceId) === deviceId;
      window.REMOTE_APP.devices = devices().filter((device) => Number(device.device_id) !== deviceId);
      button.closest('.device-row')?.remove();
      const remaining = devices();
      const connected = remaining.filter((device) => device.connection_status !== 'offline').length;
      $('#deviceCount').textContent = `${connected} connected`;
      if (removedWasActive) {
        state.activeDeviceId = remaining[0]?.device_id ?? null;
        if (remaining[0]) setActiveDevice(remaining[0].device_id);
        else $('#commandFeedback').textContent = 'Add a device to start sending commands.';
      }
      addActivity('Device removed', deviceName);
      showToast(`${deviceName} removed.`);
    } catch (error) {
      button.disabled = false;
      button.textContent = 'Remove';
      showToast(error.message, true);
    }
  }

  function bindRemoveButtons() {
    $$('[data-remove-device]').forEach((button) => button.addEventListener('click', (event) => {
      event.stopPropagation();
      removeDevice(button);
    }));
  }

  function bindCommands() {
    $$('[data-command]').forEach((button) => button.addEventListener('click', () => sendCommand(button.dataset.command)));
  }

  function bindAddDevice() {
    $('#addDeviceForm')?.addEventListener('submit', async (event) => {
      event.preventDefault();
      const form = event.currentTarget;
      const message = $('#formMessage');
      message.textContent = '';
      if (!form.checkValidity() || !$('#deviceType').value) {
        message.textContent = 'Complete all fields before adding the device.';
        form.reportValidity();
        return;
      }
      const formData = new FormData(form);
      const submit = $('button[type="submit"]', form);
      submit.disabled = true;
      submit.textContent = 'Adding…';
      try {
        await api({ action: 'add-device', name: formData.get('name'), type: formData.get('type'), brand: formData.get('brand') });
        closeModal('addDeviceModal');
        showToast('Device added. Refreshing your workspace…');
        window.setTimeout(() => window.location.reload(), 650);
      } catch (error) {
        message.textContent = error.message;
      } finally {
        submit.disabled = false;
        submit.textContent = 'Add device';
      }
    });
  }

  function bindProfileActions() {
    $$('[data-profile-action]').forEach((button) => button.addEventListener('click', () => {
      if (button.dataset.profileAction === 'clear-search') {
        const input = $('#searchInput');
        if (input) {
          input.value = '';
          input.dispatchEvent(new Event('input', { bubbles: true }));
        }
        closeModal('profileModal');
        showToast('Search cleared.');
      }
    }));
  }

  function bindTheme() {
    $('#saveTheme')?.addEventListener('click', async () => {
      const message = $('#settingsMessage');
      message.textContent = 'Saving…';
      try {
        await api({ action: 'theme', deviceId: state.activeDeviceId, themeId: Number($('#themeSelect').value) });
        message.textContent = 'Theme saved for this device.';
        showToast('Theme saved.');
      } catch (error) {
        message.textContent = error.message;
      }
    });
  }

  updateLocalDateTime();
  window.setInterval(updateLocalDateTime, 60000);
  bindCustomSelects();
  bindNavigation();
  bindSearch();
  bindModals();
  bindDeviceCards();
  bindConnectionButtons();
  bindRemoveButtons();
  bindCommands();
  bindAddDevice();
  bindProfileActions();
  bindTheme();
  bindAppChips();
})();
