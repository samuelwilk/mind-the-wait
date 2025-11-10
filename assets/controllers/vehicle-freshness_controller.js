import { Controller } from '@hotwired/stimulus';
import * as Turbo from '@hotwired/turbo';

export default class extends Controller {
    static values = {
        mercureUrl: String
    }

    static targets = ['connectionStatus', 'connectionDot']

    connect() {
        // Tick immediately, then every second
        this.tick = this.tick.bind(this);

        this.tick();
        this.timer = setInterval(this.tick, 1000);

        // Connect to Mercure if URL is provided
        if (this.hasMercureUrlValue) {
            console.log('[vehicle-freshness] Connecting to Mercure:', this.mercureUrlValue);
            this.eventSource = new EventSource(this.mercureUrlValue);
            this.lastMessageAt = null;

            // Store reference globally for debugging
            window.debugEventSource = this.eventSource;

            this.eventSource.onopen = () => {
                console.log('[vehicle-freshness] EventSource connection opened, readyState:', this.eventSource.readyState);
                this.updateConnectionStatus('connected');
            };

            this.eventSource.onmessage = (event) => {
                console.log('[vehicle-freshness] !!! RECEIVED MESSAGE !!!', event.data.substring(0, 100));
                this.lastMessageAt = Date.now();

                // Manually render the Turbo Stream
                try {
                    Turbo.renderStreamMessage(event.data);
                    console.log('[vehicle-freshness] Rendered Turbo Stream successfully');
                } catch (error) {
                    console.error('[vehicle-freshness] Error rendering Turbo Stream:', error);
                }
            };

            this.eventSource.onerror = (error) => {
                console.error('[vehicle-freshness] EventSource error:', error);
                console.error('[vehicle-freshness] EventSource readyState:', this.eventSource.readyState);

                if (this.eventSource.readyState === EventSource.CLOSED) {
                    this.updateConnectionStatus('disconnected');
                } else {
                    this.updateConnectionStatus('reconnecting');
                }
            };

            // Also try addEventListener as a backup
            this.eventSource.addEventListener('message', (e) => {
                console.log('[vehicle-freshness] addEventListener RECEIVED:', e.data.substring(0, 50));
            });

            console.log('[vehicle-freshness] EventSource created, readyState:', this.eventSource.readyState);
            console.log('[vehicle-freshness] EventSource URL:', this.eventSource.url);
        }
    }

    disconnect() {
        if (this.timer) clearInterval(this.timer);

        if (this.eventSource) {
            console.log('[vehicle-freshness] Disconnecting from Mercure');
            this.eventSource.close();
        }
    }

    tick() {
        const now = Math.floor(Date.now() / 1000);
        // IMPORTANT: Re-query the DOM every tick to get fresh elements after Turbo Stream updates
        const cards = this.element.querySelectorAll('[data-vehicle-id][data-vehicle-timestamp]');

        console.log(`[vehicle-freshness] Tick: found ${cards.length} cards`);

        cards.forEach(card => {
            const vehicleId = card.dataset.vehicleId;
            const ts = Number(card.dataset.vehicleTimestamp);
            const display = card.querySelector('[data-vehicle-freshness-target="display"]');

            console.log(`[vehicle-freshness] Vehicle ${vehicleId}: ts=${ts}, now=${now}, age=${now - ts}s`);

            if (!display || !Number.isFinite(ts)) return;

            const age = Math.max(0, now - ts);
            display.textContent = `${age}s ago`;

            display.classList.remove('text-success-600', 'text-warning-600', 'text-danger-600', 'text-gray-500');
            if (age < 60) display.classList.add('text-success-600');
            else if (age < 120) display.classList.add('text-warning-600');
            else display.classList.add('text-danger-600');
        });

        // Check if connection is stale (no messages in 15 seconds)
        if (this.lastMessageAt && (Date.now() - this.lastMessageAt) > 15000) {
            this.updateConnectionStatus('stale');
        }
    }

    updateConnectionStatus(status) {
        if (!this.hasConnectionStatusTarget) return;

        const statusConfig = {
            connected: {
                text: 'Live',
                dotClass: 'bg-success-600',
                textClass: 'text-success-700'
            },
            disconnected: {
                text: 'Disconnected',
                dotClass: 'bg-danger-600',
                textClass: 'text-danger-700'
            },
            reconnecting: {
                text: 'Reconnecting...',
                dotClass: 'bg-warning-600',
                textClass: 'text-warning-700'
            },
            stale: {
                text: 'Stale',
                dotClass: 'bg-gray-400',
                textClass: 'text-gray-600'
            }
        };

        const config = statusConfig[status] || statusConfig.disconnected;

        // Update text
        this.connectionStatusTarget.textContent = config.text;
        this.connectionStatusTarget.className = `text-xs font-medium ${config.textClass}`;

        // Update dot
        if (this.hasConnectionDotTarget) {
            this.connectionDotTarget.className = `w-2 h-2 rounded-full ${config.dotClass} ${status === 'connected' ? 'animate-pulse' : ''}`;
        }
    }
}
