import { Controller } from '@hotwired/stimulus';

/*
 * Subscribe/unsubscribe the browser to Web Push notifications for a public
 * group page. Registers the root-scoped service worker (/push-sw.js), manages
 * the PushManager subscription and syncs it with the backend.
 */
export default class extends Controller {
    static targets = ['button'];
    static values = {
        vapidPublicKey: String,
        subscribeUrl: String,
        unsubscribeUrl: String,
    };

    async connect() {
        this.subscription = null;

        if (!this.isSupported()) {
            this.element.hidden = true;
            return;
        }

        if (Notification.permission === 'denied') {
            this.element.hidden = false;
            this.render('blocked');
            return;
        }

        try {
            this.registration = await navigator.serviceWorker.register('/push-sw.js');
            this.subscription = await this.registration.pushManager.getSubscription();
        } catch (e) {
            this.element.hidden = true;
            return;
        }

        this.element.hidden = false;
        this.render(this.subscription ? 'on' : 'off');
    }

    isSupported() {
        return (
            'serviceWorker' in navigator &&
            'PushManager' in window &&
            'Notification' in window &&
            Boolean(this.vapidPublicKeyValue)
        );
    }

    async toggle() {
        if (this.busy) return;
        this.busy = true;
        this.render('working');

        try {
            if (this.subscription) {
                await this.unsubscribe();
            } else {
                await this.subscribe();
            }
        } catch (e) {
            this.render(this.subscription ? 'on' : 'off');
        } finally {
            this.busy = false;
        }
    }

    async subscribe() {
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            this.render(permission === 'denied' ? 'blocked' : 'off');
            return;
        }

        this.subscription = await this.registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: this.urlBase64ToUint8Array(this.vapidPublicKeyValue),
        });

        const res = await fetch(this.subscribeUrlValue, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(this.subscription.toJSON()),
        });

        if (!res.ok) {
            await this.subscription.unsubscribe();
            this.subscription = null;
            this.render('off');
            return;
        }

        this.render('on');
    }

    async unsubscribe() {
        const endpoint = this.subscription.endpoint;

        await fetch(this.unsubscribeUrlValue, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ endpoint }),
        });

        await this.subscription.unsubscribe();
        this.subscription = null;
        this.render('off');
    }

    render(state) {
        if (!this.hasButtonTarget) return;
        const btn = this.buttonTarget;
        btn.disabled = state === 'working' || state === 'blocked';

        const labels = {
            on: '🔕 Benachrichtigungen aus',
            off: '🔔 Benachrichtigungen an',
            working: '…',
            blocked: '🔔 Benachrichtigungen blockiert',
        };
        btn.textContent = labels[state] || labels.off;
        btn.classList.toggle('is-on', state === 'on');
    }

    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const raw = window.atob(base64);
        const output = new Uint8Array(raw.length);
        for (let i = 0; i < raw.length; ++i) {
            output[i] = raw.charCodeAt(i);
        }
        return output;
    }
}
