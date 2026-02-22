/**
 * <airlock-queue-state> — queue-state stream component.
 *
 * Subscribes to a global Mercure topic and emits:
 * - airlock-queue-state: { queueSize, position, userState, candidateId, source }
 *
 * Optional:
 * - bindClient(client): bind an AirlockClient instance for richer own-state updates.
 * - render attribute: show a tiny built-in view for quick debugging.
 */
class AirlockQueueState extends HTMLElement {
    static get observedAttributes() {
        return ['hub-url', 'topic', 'token', 'client-id', 'render'];
    }

    constructor() {
        super();
        this._eventSource = null;
        this._client = null;
        this._boundClientHandlers = null;

        this._state = {
            queueSize: 0,
            position: null,
            userState: 'idle', // idle | queued | claiming | admitted | error
            candidateId: null,
            source: 'init',
        };

        this.attachShadow({ mode: 'open' });
    }

    connectedCallback() {
        this._render();
        this._connectIfReady();
        this._emitState('init');
    }

    disconnectedCallback() {
        this._disconnect();
        this._unbindClient();
    }

    attributeChangedCallback(name) {
        if (name === 'hub-url' || name === 'topic' || name === 'token') {
            this._disconnect();
            this._connectIfReady();
            return;
        }

        if (name === 'client-id') {
            this._recomputePosition();
            this._emitState('attr');
            return;
        }

        if (name === 'render') {
            this._render();
            this._updateView();
        }
    }

    bindClient(client) {
        this._unbindClient();
        this._client = client ?? null;

        if (!this._client || typeof this._client.addEventListener !== 'function') {
            return;
        }

        const onClientState = (event) => {
            const detail = event.detail ?? {};
            const lifecycle = detail.lifecycle ?? null;

            if (lifecycle === 'queued') {
                this._state.userState = 'queued';
                if (Number.isInteger(detail.position)) {
                    this._state.position = detail.position;
                    this._state.queueSize = Math.max(this._state.queueSize, detail.position);
                }
            } else if (lifecycle === 'claiming') {
                this._state.userState = 'claiming';
            } else if (lifecycle === 'admitted') {
                this._state.userState = 'admitted';
                this._state.position = null;
            } else if (lifecycle === 'idle') {
                this._state.userState = 'idle';
                this._state.position = null;
            } else if (lifecycle === 'error') {
                this._state.userState = 'error';
            }

            this._emitState('client');
        };

        const onTurn = () => {
            this._state.userState = 'claiming';
            this._emitState('client');
        };

        const onAdmitted = () => {
            this._state.userState = 'admitted';
            this._state.position = null;
            this._emitState('client');
        };

        this._boundClientHandlers = { onClientState, onTurn, onAdmitted };
        this._client.addEventListener('state', onClientState);
        this._client.addEventListener('turn', onTurn);
        this._client.addEventListener('admitted', onAdmitted);
    }

    getState() {
        return { ...this._state };
    }

    _connectIfReady() {
        if (!this.isConnected) {
            return;
        }

        if (this._eventSource) {
            return;
        }

        const hubUrl = this.getAttribute('hub-url');
        const topic = this.getAttribute('topic');

        if (!hubUrl || !topic) {
            return;
        }

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
        this._eventSource.onmessage = (event) => this._handleMessage(event);
    }

    _disconnect() {
        if (!this._eventSource) {
            return;
        }

        this._eventSource.close();
        this._eventSource = null;
    }

    _unbindClient() {
        if (!this._client || !this._boundClientHandlers) {
            return;
        }

        this._client.removeEventListener('state', this._boundClientHandlers.onClientState);
        this._client.removeEventListener('turn', this._boundClientHandlers.onTurn);
        this._client.removeEventListener('admitted', this._boundClientHandlers.onAdmitted);

        this._boundClientHandlers = null;
    }

    _handleMessage(event) {
        let data;
        try {
            data = JSON.parse(event.data);
        } catch {
            return;
        }

        const identifier = typeof data.identifier === 'string' ? data.identifier : null;
        const clientId = this.getAttribute('client-id');

        switch (data.event) {
            case 'entry_queued':
                this._state.queueSize += 1;
                if (identifier !== null && identifier === clientId) {
                    this._state.userState = 'queued';
                }
                break;
            case 'entry_admitted':
                this._state.queueSize = Math.max(0, this._state.queueSize - 1);
                this._state.candidateId = identifier;
                if (identifier !== null && identifier === clientId) {
                    this._state.userState = 'admitted';
                    this._state.position = null;
                }
                break;
            case 'user_left':
                this._state.queueSize = Math.max(0, this._state.queueSize - 1);
                if (identifier !== null && identifier === clientId) {
                    this._state.userState = 'idle';
                    this._state.position = null;
                }
                break;
            case 'lock_released':
                this._state.candidateId = null;
                break;
            default:
                return;
        }

        this._recomputePosition();
        this._emitState('stream');
    }

    _recomputePosition() {
        if (this._state.userState !== 'queued') {
            this._state.position = null;
            return;
        }

        const clientId = this.getAttribute('client-id');
        if (clientId !== null && this._state.candidateId === clientId) {
            this._state.position = 1;
            return;
        }

        // Lottery queue position approximation:
        // position is effectively "how many waiting users are in the pool".
        this._state.position = Math.max(1, this._state.queueSize);
    }

    _emitState(source) {
        this._state.source = source;
        this._updateView();

        this.dispatchEvent(new CustomEvent('airlock-queue-state', {
            bubbles: true,
            detail: this.getState(),
        }));
    }

    _render() {
        if (!this.hasAttribute('render')) {
            this.shadowRoot.innerHTML = '';
            return;
        }

        this.shadowRoot.innerHTML = `
            <style>
              :host {
                display: block;
                font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
                font-size: 12px;
                color: oklch(0.7 0.02 250);
              }
              .meta {
                border: 1px solid oklch(0.35 0.03 250 / 0.45);
                border-radius: 0.75rem;
                padding: 0.5rem 0.75rem;
                background: oklch(0.18 0.02 250 / 0.35);
              }
            </style>
            <div class="meta" part="meta"></div>
        `;
    }

    _updateView() {
        const meta = this.shadowRoot.querySelector('.meta');
        if (!meta) {
            return;
        }

        meta.textContent = [
            `state=${this._state.userState}`,
            `queue=${this._state.queueSize}`,
            `position=${this._state.position ?? '-'}`,
            `source=${this._state.source}`,
        ].join(' | ');
    }
}

customElements.define('airlock-queue-state', AirlockQueueState);

export { AirlockQueueState };
