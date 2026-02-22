/**
 * <airlock-join-button> — reusable join button for Airlock flows.
 *
 * Default behavior:
 * - emits `airlock-join-click`
 * - if a client is bound via bindClient(), calls client.start()
 * - emits `airlock-join-result` with start() payload
 *
 * Optional manual mode:
 * - set `manual` attribute to disable auto start()
 * - host app handles click and can call setState(...)
 */
class AirlockJoinButton extends HTMLElement {
    static get observedAttributes() {
        return [
            'state',
            'disabled',
            'button-class',
            'idle-label',
            'joining-label',
            'queued-label',
            'claiming-label',
            'admitted-label',
            'error-label',
            'manual',
        ];
    }

    constructor() {
        super();
        this._button = null;
        this._client = null;
        this._boundClientState = null;
        this._state = this._normalizeState(this.getAttribute('state') ?? 'idle');
        this._pending = false;
        this._onClick = this._handleClick.bind(this);
    }

    connectedCallback() {
        this._ensureButton();
        this._syncButton();
    }

    disconnectedCallback() {
        if (this._button) {
            this._button.removeEventListener('click', this._onClick);
        }
        this._unbindClient();
    }

    attributeChangedCallback(name, _oldValue, newValue) {
        if (name === 'state') {
            this._state = this._normalizeState(newValue ?? 'idle');
        }

        if (!this.isConnected) {
            return;
        }

        if (name === 'button-class') {
            this._applyButtonClass();
            return;
        }

        this._syncButton();
    }

    bindClient(client) {
        this._unbindClient();
        this._client = client ?? null;

        if (!this._client || typeof this._client.addEventListener !== 'function') {
            return;
        }

        this._boundClientState = (event) => {
            const lifecycle = event?.detail?.lifecycle;
            if (typeof lifecycle !== 'string') {
                return;
            }

            this.setState(lifecycle);
        };

        this._client.addEventListener('state', this._boundClientState);

        if (typeof this._client.getState === 'function') {
            const clientState = this._client.getState();
            if (typeof clientState?.lifecycle === 'string') {
                this.setState(clientState.lifecycle);
            }
        }
    }

    setState(state) {
        this._state = this._normalizeState(state);
        this.setAttribute('state', this._state);
        this._syncButton();
    }

    getState() {
        return this._state;
    }

    _unbindClient() {
        if (!this._client || !this._boundClientState) {
            return;
        }

        this._client.removeEventListener('state', this._boundClientState);
        this._boundClientState = null;
    }

    _ensureButton() {
        const existing = this.querySelector('button[data-airlock-join], button');

        if (existing) {
            this._button = existing;
        } else {
            this._button = document.createElement('button');
            this._button.type = 'button';
            this._button.setAttribute('data-airlock-join', '');
            this.appendChild(this._button);
        }

        this._button.addEventListener('click', this._onClick);
        this._applyButtonClass();
    }

    _applyButtonClass() {
        if (!this._button) {
            return;
        }

        const klass = this.getAttribute('button-class');
        if (klass !== null) {
            this._button.className = klass;
        }
    }

    async _handleClick() {
        if (!this._button || this._button.disabled) {
            return;
        }

        this.dispatchEvent(new CustomEvent('airlock-join-click', {
            bubbles: true,
            detail: {
                state: this._state,
            },
        }));

        if (this.hasAttribute('manual') || !this._client) {
            return;
        }

        if (this._pending) {
            return;
        }

        this._pending = true;
        this.setState('joining');

        try {
            const result = await this._client.start();
            this.dispatchEvent(new CustomEvent('airlock-join-result', {
                bubbles: true,
                detail: result ?? null,
            }));
        } catch (error) {
            this.setState('error');
            this.dispatchEvent(new CustomEvent('airlock-join-result', {
                bubbles: true,
                detail: {
                    ok: false,
                    error: error instanceof Error ? error.message : String(error),
                },
            }));
        } finally {
            this._pending = false;
            this._syncButton();
        }
    }

    _syncButton() {
        if (!this._button) {
            return;
        }

        const labels = this._labels();
        const label = labels[this._state] ?? labels.idle;
        const hardDisabled = this.hasAttribute('disabled');
        const stateDisabled = this._state === 'joining'
            || this._state === 'queued'
            || this._state === 'claiming'
            || this._state === 'admitted';

        this._button.textContent = label;
        this._button.disabled = hardDisabled || this._pending || stateDisabled;
        this._button.setAttribute('aria-busy', this._pending ? 'true' : 'false');
    }

    _labels() {
        return {
            idle: this.getAttribute('idle-label') ?? 'Join Queue',
            joining: this.getAttribute('joining-label') ?? 'Joining...',
            queued: this.getAttribute('queued-label') ?? 'Queued',
            claiming: this.getAttribute('claiming-label') ?? 'Claiming...',
            admitted: this.getAttribute('admitted-label') ?? 'Inside',
            error: this.getAttribute('error-label') ?? 'Retry Join',
        };
    }

    _normalizeState(state) {
        switch (state) {
            case 'joining':
            case 'queued':
            case 'claiming':
            case 'admitted':
            case 'error':
                return state;
            default:
                return 'idle';
        }
    }
}

customElements.define('airlock-join-button', AirlockJoinButton);

export { AirlockJoinButton };
