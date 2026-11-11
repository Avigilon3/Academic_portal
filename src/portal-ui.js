const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function initCountUps() {
  const values = document.querySelectorAll('.dashboard-stats .stat-value, .chart-total');
  const animate = (element) => {
    const original = element.textContent.trim();
    const match = original.match(/^(-?[\d,]+(?:\.\d+)?)(\s*%?)$/);
    if (!match) return;

    const target = Number(match[1].replace(/,/g, ''));
    if (!Number.isFinite(target)) return;
    if (reducedMotion) return;

    const decimals = (match[1].split('.')[1] || '').length;
    const suffix = match[2];
    const start = performance.now();
    const duration = 650;

    function tick(now) {
      const progress = Math.min((now - start) / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      const current = target * eased;
      element.textContent = `${current.toLocaleString(undefined, {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
      })}${suffix}`;
      if (progress < 1) requestAnimationFrame(tick);
      else element.textContent = original;
    }

    requestAnimationFrame(tick);
  };

  if (!('IntersectionObserver' in window)) {
    values.forEach(animate);
    return;
  }

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      observer.unobserve(entry.target);
      animate(entry.target);
    });
  }, { threshold: 0.2 });
  values.forEach((element) => observer.observe(element));
}

function initChartTooltips() {
  document.querySelectorAll('.bar[title], .bar-col, [data-chart-point]').forEach((point) => {
    if (point.hasAttribute('title')) return;
    const label = point.closest('.bar-wrap, .bar-group, [data-chart-point]')?.querySelector('.bar-label, .bar-caption')?.textContent.trim();
    const series = point.classList.contains('you') ? 'Your result' : point.classList.contains('class') ? 'Class average' : 'Chart value';
    const height = Number.parseFloat(point.style.height);
    point.title = `${label ? `${label} — ` : ''}${series}${Number.isFinite(height) ? `: ${Math.round(height)}% of chart scale` : ''}`;
    point.setAttribute('aria-label', point.title);
  });
}

function initTableTools() {
  const content = document.querySelector('main.content');
  if (!content || content.querySelector('input[type="search"], input[id*="search" i], input[placeholder*="search" i]')) return;

  content.querySelectorAll('table:not(.grading-sheet-table):not(.standing-table)').forEach((table) => {
    const body = table.tBodies[0];
    const header = table.tHead;
    if (!body || !header || header.rows.length !== 1 || table.closest('form')) return;

    const rows = Array.from(body.rows).filter((row) => !row.hidden && !row.classList.contains('no-results-row'));
    if (rows.length < 8) return;

    const controls = document.createElement('div');
    controls.className = 'portal-table-tools';
    const search = document.createElement('input');
    search.type = 'search';
    search.className = 'portal-table-tools__search';
    search.setAttribute('aria-label', `Search ${table.caption?.textContent.trim() || 'table'} rows`);
    search.placeholder = 'Search this table';
    controls.append(search);

    let activeStatus = '';
    const statusIndex = Array.from(header.rows[0].cells).findIndex((cell) => /^(status|request status|approval status)$/i.test(cell.textContent.trim()));
    const statuses = statusIndex < 0
      ? []
      : [...new Set(rows.map((row) => row.cells[statusIndex]?.textContent.trim()).filter(Boolean))].sort();
    const statusButtons = [];

    if (statuses.length > 1 && statuses.length <= 8) {
      const filters = document.createElement('div');
      filters.className = 'portal-table-tools__filters';
      filters.setAttribute('aria-label', 'Filter by status');
      [['', 'All'], ...statuses.map((status) => [status, status])].forEach(([value, label]) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'portal-table-tools__filter';
        button.textContent = label;
        button.setAttribute('aria-pressed', value === '' ? 'true' : 'false');
        button.addEventListener('click', () => {
          activeStatus = value;
          statusButtons.forEach((item) => item.setAttribute('aria-pressed', String(item === button)));
          applyFilter();
        });
        statusButtons.push(button);
        filters.append(button);
      });
      controls.append(filters);
    }

    const emptyMessage = document.createElement('p');
    emptyMessage.className = 'portal-table-tools__empty';
    emptyMessage.setAttribute('role', 'status');
    emptyMessage.textContent = 'No rows match your search.';
    emptyMessage.hidden = true;

    function applyFilter() {
      const query = search.value.trim().toLocaleLowerCase();
      let visible = 0;
      rows.forEach((row) => {
        const matchesText = row.textContent.toLocaleLowerCase().includes(query);
        const matchesStatus = !activeStatus || row.cells[statusIndex]?.textContent.trim() === activeStatus;
        row.hidden = !(matchesText && matchesStatus);
        if (!row.hidden) visible += 1;
      });
      emptyMessage.hidden = visible > 0;
    }

    search.addEventListener('input', applyFilter);
    table.before(controls);
    table.after(emptyMessage);
  });
}

function initCommandPalette() {
  const sidebar = document.querySelector('.sidebar');
  const content = document.querySelector('main.content');
  if (!sidebar || !content) return;

  const commands = [];
  const seen = new Set();
  document.querySelectorAll('.sidebar a.nav-item, main.content a[href]').forEach((link) => {
    const label = (link.getAttribute('aria-label') || link.textContent).replace(/\s+/g, ' ').trim();
    const href = link.getAttribute('href');
    if (!label || !href || href.startsWith('#') || link.hasAttribute('download')) return;
    const url = new URL(href, window.location.href);
    if (url.origin !== window.location.origin || seen.has(url.href)) return;
    seen.add(url.href);
    commands.push({ label, href: url.href });
  });
  if (!commands.length) return;

  const dialog = document.createElement('dialog');
  dialog.className = 'portal-command-palette';
  dialog.setAttribute('aria-label', 'Quick navigation');
  const search = document.createElement('input');
  search.type = 'search';
  search.placeholder = 'Search pages and actions...';
  search.setAttribute('aria-label', 'Search pages and actions');
  const list = document.createElement('div');
  list.className = 'portal-command-palette__results';
  list.setAttribute('role', 'listbox');
  dialog.append(search, list);
  document.body.append(dialog);

  function renderCommands() {
    const query = search.value.trim().toLocaleLowerCase();
    list.replaceChildren();
    commands
      .filter((command) => command.label.toLocaleLowerCase().includes(query))
      .slice(0, 12)
      .forEach((command) => {
        const link = document.createElement('a');
        link.className = 'portal-command-palette__item';
        link.href = command.href;
        link.setAttribute('role', 'option');
        link.textContent = command.label;
        list.append(link);
      });
    if (!list.childElementCount) {
      const empty = document.createElement('p');
      empty.className = 'portal-command-palette__empty';
      empty.textContent = 'No matching pages or actions.';
      list.append(empty);
    }
  }

  search.addEventListener('input', renderCommands);
  search.addEventListener('keydown', (event) => {
    if (event.key !== 'ArrowDown' && event.key !== 'Enter') return;
    const first = list.querySelector('a');
    if (!first) return;
    event.preventDefault();
    if (event.key === 'Enter') first.click();
    else first.focus();
  });
  dialog.addEventListener('click', (event) => {
    if (event.target === dialog) dialog.close();
  });
  dialog.addEventListener('close', () => {
    search.value = '';
    renderCommands();
  });
  renderCommands();

  document.addEventListener('keydown', (event) => {
    if (!(event.ctrlKey || event.metaKey) || event.key.toLocaleLowerCase() !== 'k') return;
    event.preventDefault();
    if (!dialog.open) {
      dialog.showModal();
      search.focus();
    }
  });
}

function initToastApi() {
  window.portalToast = (message, type = 'info') => {
    if (typeof message !== 'string' || !message.trim()) return;
    document.querySelectorAll('.portal-toast').forEach((toast) => toast.remove());
    const toast = document.createElement('div');
    toast.className = `portal-toast portal-toast--${['success', 'error', 'warning'].includes(type) ? type : 'info'}`;
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
    toast.textContent = message;
    document.body.append(toast);
    window.setTimeout(() => toast.remove(), 4200);
  };
}

function initPortalUi() {
  initCountUps();
  initChartTooltips();
  initTableTools();
  initCommandPalette();
  initToastApi();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initPortalUi, { once: true });
} else {
  initPortalUi();
}
