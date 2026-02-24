/**
 * Headless client for Airlock queue flows (join, turn notifications, claim, release).
 *
 * Emits CustomEvents:
 * - state: { lifecycle, position, reservationNonce, topic, hubUrl, token, error }
 * - queued: start() response payload
 * - admitted: start()/claim() response payload
 * - turn: { claimNonce, payload }
 * - claimed: claim() response payload
 * - released: release() response payload (or null)
 * - error: { stage, error, details }
 */
class AirlockClient extends EventTarget {
    constructor({
        clientId,
        startUrl,
        claimUrl,
        releaseUrl,
    }) {
        super();

        this._clientId = clientId;
        this._startUrl = startUrl;
        this._claimUrl = claimUrl;
        this._releaseUrl = releaseUrl;

        this._eventSource = null;
        this._state = {
            lifecycle: 'idle',
            position: null,
            reservationNonce: null,
            topic: null,
            hubUrl: null,
            token: null,
            error: null,
        };
    }

    get clientId() {
        return this._clientId;
    }

    getState() {
        return { ...this._state };
    }

    async start() {
        this._setState({ lifecycle: 'joining', error: null });

        let data;
        try {
            const response = await fetch(this._startUrl, {
                method: 'POST',
                headers: {
                    'X-Client-Id': this._clientId,
                },
            });
            data = await response.json();
        } catch (error) {
            this._setError('start', error);
            return {
                ok: false,
                error: error instanceof Error ? error.message : 'network_error',
            };
        }

        if (!data?.ok) {
            this._setError('start', data?.error ?? 'start_failed');

            return data;
        }

        if (data.status === 'admitted') {
            this.disconnectTurnStream();
            this._setState({
                lifecycle: 'admitted',
                position: null,
                reservationNonce: null,
                topic: data.topic ?? null,
                hubUrl: data.hubUrl ?? null,
                token: data.token ?? null,
                error: null,
            });
            this._emit('admitted', data);

            return data;
        }

        this._setState({
            lifecycle: 'queued',
            position: Number.isInteger(data.position) ? data.position : null,
            reservationNonce: typeof data.reservationNonce === 'string' && data.reservationNonce !== ''
                ? data.reservationNonce
                : null,
            topic: data.topic ?? null,
            hubUrl: data.hubUrl ?? null,
            token: data.token ?? null,
            error: null,
        });
        this._emit('queued', data);

        if (data.hubUrl && data.topic) {
            this.connectTurnStream(data.hubUrl, data.topic, data.token ?? null);
        }

        return data;
    }

    async claim(claimNonce = null) {
        const nonce = typeof claimNonce === 'string' && claimNonce !== ''
            ? claimNonce
            : this._state.reservationNonce;

        if (!nonce) {
            this._setError('claim', 'missing_claim_nonce');
            return {
                ok: false,
                error: 'missing_claim_nonce',
            };
        }

        const previousState = this._state.lifecycle === 'queued' ? 'queued' : 'claiming';
        this._setState({ lifecycle: 'claiming', error: null });

        let data;
        try {
            const response = await fetch(this._claimUrl, {
                method: 'POST',
                headers: {
                    'X-Client-Id': this._clientId,
                    'X-Claim-Nonce': nonce,
                },
            });
            data = await response.json();
        } catch (error) {
            this._setState({ lifecycle: previousState });
            this._setError('claim', error);

            return {
                ok: false,
                error: error instanceof Error ? error.message : 'network_error',
            };
        }

        if (data?.ok && data.status === 'admitted') {
            this.disconnectTurnStream();
            this._setState({
                lifecycle: 'admitted',
                position: null,
                reservationNonce: null,
                topic: data.topic ?? this._state.topic,
                hubUrl: data.hubUrl ?? this._state.hubUrl,
                token: data.token ?? this._state.token,
                error: null,
            });
            this._emit('admitted', data);
            this._emit('claimed', data);

            return data;
        }

        this._setState({
            lifecycle: 'queued',
            error: data?.error ?? 'claim_failed',
        });
        this._emit('claimed', data);
        this._emit('error', {
            stage: 'claim',
            error: data?.error ?? 'claim_failed',
            details: data ?? null,
        });

        return data;
    }

    async release() {
        let data = null;
        try {
            const response = await fetch(this._releaseUrl, {
                method: 'POST',
                headers: {
                    'X-Client-Id': this._clientId,
                },
            });
            data = await response.json();
        } catch (error) {
            this._setError('release', error);

            return {
                ok: false,
                error: error instanceof Error ? error.message : 'network_error',
            };
        }

        this._setState({
            lifecycle: 'idle',
            position: null,
            reservationNonce: null,
            error: null,
        });
        this._emit('released', data);

        return data;
    }

    connectTurnStream(hubUrl, topic, token = null) {
        this.disconnectTurnStream();

        if (!hubUrl || !topic) {
            return;
        }

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
        this._eventSource.onmessage = (event) => this._handleTurnMessage(event);
        this._eventSource.onerror = () => {
            // EventSource reconnects automatically. Surface state as queued.
            if (this._state.lifecycle === 'claiming' || this._state.lifecycle === 'queued') {
                this._setState({ lifecycle: 'queued' });
            }
        };
    }

    disconnectTurnStream() {
        if (!this._eventSource) {
            return;
        }

        this._eventSource.close();
        this._eventSource = null;
    }

    reset() {
        this.disconnectTurnStream();
        this._setState({
            lifecycle: 'idle',
            position: null,
            reservationNonce: null,
            topic: null,
            hubUrl: null,
            token: null,
            error: null,
        });
    }

    _handleTurnMessage(event) {
        let payload;
        try {
            payload = JSON.parse(event.data);
        } catch {
            return;
        }

        if (payload?.event !== 'your_turn') {
            return;
        }

        const claimNonce = typeof payload.claimNonce === 'string' && payload.claimNonce !== ''
            ? payload.claimNonce
            : null;

        this._setState({
            lifecycle: 'queued',
            reservationNonce: claimNonce,
            error: null,
        });

        this._emit('turn', {
            claimNonce,
            payload,
        });
    }

    _setState(patch) {
        this._state = {
            ...this._state,
            ...patch,
        };
        this._emit('state', this.getState());
    }

    _setError(stage, errorOrMessage) {
        const error = errorOrMessage instanceof Error ? errorOrMessage.message : String(errorOrMessage);
        this._setState({
            lifecycle: 'error',
            error,
        });
        this._emit('error', {
            stage,
            error,
            details: errorOrMessage,
        });
    }

    _emit(name, detail) {
        this.dispatchEvent(new CustomEvent(name, { detail }));
    }
}

export { AirlockClient };
