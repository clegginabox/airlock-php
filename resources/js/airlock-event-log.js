/**
 * <airlock-event-log> — real-time event log web component for Airlock.
 *
 * Connects to a Mercure hub via SSE and displays airlock events
 * (admitted, queued, left, released) in a scrollable, colour-coded log.
 *
 * @example
 * <!-- Auto-connect to Mercure -->
 * <airlock-event-log
 *   hub-url="http://localhost/.well-known/mercure"
 *   topic="redis-lottery-queue"
 *   token="eyJ..."
 * ></airlock-event-log>
 *
 * @example
 * <!-- Programmatic use -->
 * <airlock-event-log placeholder="Waiting for events..."></airlock-event-log>
 * <script>
 *   const log = document.querySelector('airlock-event-log');
 *   log.addEntry('success', 'bot-0001 admitted');
 * </script>
 */
class AirlockEventLog extends HTMLElement {

  static get observedAttributes() {
    return ['hub-url', 'topic', 'token', 'max-entries', 'placeholder'];
  }

  constructor() {
    super();
    this._eventSource = null;
    this._entryCount = 0;
    this.attachShadow({ mode: 'open' });
  }

  /* ------------------------------------------------------------------ */
  /*  Lifecycle                                                          */
  /* ------------------------------------------------------------------ */

  connectedCallback() {
    this._render();
    this._connectIfReady();
  }

  disconnectedCallback() {
    this._disconnect();
  }

  attributeChangedCallback(name) {
    if (name === 'placeholder' && this._placeholder) {
      this._placeholder.textContent = this.getAttribute('placeholder') || 'No events yet';
    }
    if (name === 'hub-url' || name === 'topic' || name === 'token') {
      this._disconnect();
      this._connectIfReady();
    }
  }

  /* ------------------------------------------------------------------ */
  /*  Public API                                                         */
  /* ------------------------------------------------------------------ */

  /**
   * Add a log entry.
   *
   * @param {'success'|'warning'|'error'|'info'} type
   * @param {string} text  — may contain HTML
   */
  addEntry(type, text) {
    const container = this._log;
    if (!container) {
      return;
    }

    this._placeholder?.classList.add('hidden');

    const max = parseInt(this.getAttribute('max-entries'), 10) || 200;
    const entry = document.createElement('div');
    entry.className = 'entry entry-' + (type || 'info');

    const ts = document.createElement('span');
    ts.className = 'ts';
    ts.textContent = this._timestamp();

    const msg = document.createElement('span');
    msg.className = 'msg';
    msg.innerHTML = text;

    entry.appendChild(ts);
    entry.appendChild(msg);
    container.prepend(entry);
    this._entryCount++;

    while (this._entryCount > max) {
      container.removeChild(container.lastChild);
      this._entryCount--;
    }

    this.dispatchEvent(new CustomEvent('airlock-log-entry', {
      bubbles: true,
      detail: { type, text, timestamp: new Date().toISOString() },
    }));
  }

  /** Clear every entry and restore the placeholder. */
  clear() {
    if (this._log) {
      this._log.innerHTML = '';
    }
    this._entryCount = 0;
    this._placeholder?.classList.remove('hidden');
  }

  /* ------------------------------------------------------------------ */
  /*  Mercure SSE                                                        */
  /* ------------------------------------------------------------------ */

  /** @private */
  _connectIfReady() {
    // Guard: don't connect until the shadow DOM has been rendered
    if (!this._log) {
      return;
    }

    this._disconnect();

    const hubUrl = this.getAttribute('hub-url');
    const topic = this.getAttribute('topic');
    if (!hubUrl || !topic) return;

    const token = this.getAttribute('token');
    if (token) {
      const path = new URL(hubUrl).pathname;
      document.cookie =
        'mercureAuthorization=' + encodeURIComponent(token) +
        '; path=' + path +
        '; SameSite=Strict';
    }

    const url = new URL(hubUrl);
    url.searchParams.append('topic', topic);

    this._eventSource = new EventSource(url, { withCredentials: true });
    this._eventSource.onmessage = (e) => this._handleMessage(e);
  }

  /** @private */
  _disconnect() {
    if (this._eventSource) {
      this._eventSource.close();
      this._eventSource = null;
    }
  }

  /** @private */
  _handleMessage(event) {
    let data;
    try { data = JSON.parse(event.data); } catch { return; }

    const id = data.identifier
      ? '<strong>' + this._esc(data.identifier.substring(0, 32)) + '</strong>'
      : '';

    switch (data.event) {
      case 'entry_admitted':
        this.addEntry('success', id + ' entered the airlock');
        break;
      case 'entry_queued':
        this.addEntry('warning', id + ' joined the queue');
        break;
      case 'user_left':
        this.addEntry('info', id + ' left the queue');
        break;
      case 'lock_released':
        this.addEntry('info', 'lock released');
        break;
      default:
        this.addEntry('info', this._esc(data.event || 'unknown event'));
    }
  }

  /* ------------------------------------------------------------------ */
  /*  Rendering                                                          */
  /* ------------------------------------------------------------------ */

  /** @private */
  _render() {
    const placeholder = this.getAttribute('placeholder') || 'No events yet';

    this.shadowRoot.innerHTML = `
      <style>${AirlockEventLog._styles()}</style>
      <div class="wrapper" part="wrapper">
        <div class="log" part="log"></div>
        <p class="placeholder" part="placeholder">${this._esc(placeholder)}</p>
      </div>
    `;

    this._log = this.shadowRoot.querySelector('.log');
    this._placeholder = this.shadowRoot.querySelector('.placeholder');
  }

  /** @private */
  static _styles() {
    return `
      :host {
        display: block;
        --airlock-bg:             var(--color-base-100, #1d232a);
        --airlock-border:         var(--color-base-300, #374151);
        --airlock-text:           var(--color-base-content, #a6adbb);
        --airlock-text-muted:     oklch(from var(--airlock-text) l c h / 0.4);
        --airlock-radius:         1rem;
        --airlock-max-height:     14rem;
        --airlock-font-size:      0.8125rem;
        --airlock-font-family:    ui-sans-serif, system-ui, sans-serif;
        --airlock-ts-font:        ui-monospace, SFMono-Regular, Menlo, monospace;
        --airlock-success:        var(--color-success, #36d399);
        --airlock-warning:        var(--color-warning, #fbbd23);
        --airlock-error:          var(--color-error,   #f87272);
        --airlock-info:           var(--color-info,    #3abff8);
      }

      .wrapper {
        border-radius: var(--airlock-radius);
        overflow: hidden;
      }

      .log {
        max-height: var(--airlock-max-height);
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 2px;
        scrollbar-width: thin;
      }

      .placeholder {
        font-family: var(--airlock-font-family);
        font-size: var(--airlock-font-size);
        color: var(--airlock-text-muted);
        margin: 0;
        padding: 0;
      }
      .placeholder.hidden { display: none; }

      /* Entries */
      .entry {
        display: flex;
        align-items: baseline;
        gap: 0.5rem;
        padding: 0.25rem 0.5rem;
        border-radius: 0.375rem;
        font-family: var(--airlock-font-family);
        font-size: var(--airlock-font-size);
        line-height: 1.5;
        animation: slide-in 0.2s ease-out;
      }

      .entry-success {
        background: oklch(from var(--airlock-success) l c h / 0.1);
        color: var(--airlock-success);
      }
      .entry-warning {
        background: oklch(from var(--airlock-warning) l c h / 0.1);
        color: var(--airlock-warning);
      }
      .entry-error {
        background: oklch(from var(--airlock-error) l c h / 0.1);
        color: var(--airlock-error);
      }
      .entry-info {
        background: oklch(from var(--airlock-info) l c h / 0.1);
        color: var(--airlock-info);
      }

      .ts {
        font-family: var(--airlock-ts-font);
        font-size: 0.7rem;
        opacity: 0.6;
        flex-shrink: 0;
      }

      .msg {
        flex: 1;
        min-width: 0;
      }
      .msg strong {
        font-weight: 700;
      }

      @keyframes slide-in {
        from { opacity: 0; transform: translateY(-4px); }
        to   { opacity: 1; transform: translateY(0); }
      }
    `;
  }

  /* ------------------------------------------------------------------ */
  /*  Helpers                                                            */
  /* ------------------------------------------------------------------ */

  /** @private */
  _timestamp() {
    const d = new Date();
    return (
      String(d.getHours()).padStart(2, '0') + ':' +
      String(d.getMinutes()).padStart(2, '0') + ':' +
      String(d.getSeconds()).padStart(2, '0')
    );
  }

  /** @private */
  _esc(str) {
    const el = document.createElement('span');
    el.textContent = str;
    return el.innerHTML;
  }
}

customElements.define('airlock-event-log', AirlockEventLog);

export { AirlockEventLog };
