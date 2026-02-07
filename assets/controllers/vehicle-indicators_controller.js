import { Controller } from '@hotwired/stimulus';
import * as Turbo from '@hotwired/turbo';

/**
 * Handles real-time vehicle indicator updates via Mercure.
 *
 * Connects to Mercure hub and renders Turbo Stream updates
 * to keep vehicle pills in sync with live positions.
 */
export default class extends Controller {
    static values = {
        mercureUrl: String,
        routeId: String
    }

    static targets = ['connectionStatus', 'connectionDot', 'container', 'pillsContainer', 'fadeRight']

    connect() {
        this.lastMessageAt = null;

        // Start stale check timer
        this.staleCheckTimer = setInterval(() => this.checkStale(), 5000);

        // Connect to Mercure if URL is provided
        if (this.hasMercureUrlValue && this.mercureUrlValue) {
            this.connectToMercure();
        }

        // Check if we need to show the fade indicator
        this.checkScrollFade();
    }

    disconnect() {
        if (this.staleCheckTimer) {
            clearInterval(this.staleCheckTimer);
        }

        if (this.eventSource) {
            this.eventSource.close();
        }
    }

    connectToMercure() {
        console.log('[vehicle-indicators] Connecting to Mercure:', this.mercureUrlValue);
        this.eventSource = new EventSource(this.mercureUrlValue);

        this.eventSource.onopen = () => {
            console.log('[vehicle-indicators] Connected');
            this.updateConnectionStatus('connected');
        };

        this.eventSource.onmessage = (event) => {
            console.log('[vehicle-indicators] Received update');
            this.lastMessageAt = Date.now();

            try {
                Turbo.renderStreamMessage(event.data);
                // Re-check scroll fade after DOM update
                requestAnimationFrame(() => this.checkScrollFade());
            } catch (error) {
                console.error('[vehicle-indicators] Error rendering update:', error);
            }
        };

        this.eventSource.onerror = (error) => {
            console.error('[vehicle-indicators] Connection error:', error);

            if (this.eventSource.readyState === EventSource.CLOSED) {
                this.updateConnectionStatus('disconnected');
            } else {
                this.updateConnectionStatus('reconnecting');
            }
        };
    }

    checkStale() {
        // If we've received messages but none in the last 60 seconds, mark as stale
        if (this.lastMessageAt && (Date.now() - this.lastMessageAt) > 60000) {
            this.updateConnectionStatus('stale');
        }
    }

    checkScrollFade() {
        if (!this.hasPillsContainerTarget || !this.hasFadeRightTarget) return;

        const container = this.pillsContainerTarget;
        const fade = this.fadeRightTarget;

        // Show fade if content is scrollable
        if (container.scrollWidth > container.clientWidth) {
            fade.classList.remove('hidden');
        } else {
            fade.classList.add('hidden');
        }
    }

    updateConnectionStatus(status) {
        if (!this.hasConnectionStatusTarget) return;

        const statusConfig = {
            connected: {
                text: 'Live',
                dotClass: 'bg-success-500',
                textClass: 'text-success-600'
            },
            disconnected: {
                text: 'Disconnected',
                dotClass: 'bg-danger-500',
                textClass: 'text-danger-600'
            },
            reconnecting: {
                text: 'Reconnecting...',
                dotClass: 'bg-warning-500',
                textClass: 'text-warning-600'
            },
            stale: {
                text: 'Data may be stale',
                dotClass: 'bg-warning-500',
                textClass: 'text-warning-600'
            }
        };

        const config = statusConfig[status] || statusConfig.disconnected;

        // Update text
        this.connectionStatusTarget.textContent = config.text;
        this.connectionStatusTarget.className = `text-xs font-medium ${config.textClass}`;

        // Update dot
        if (this.hasConnectionDotTarget) {
            const animateClass = status === 'connected' ? 'animate-pulse' : '';
            this.connectionDotTarget.className = `w-2 h-2 rounded-full transition-colors ${config.dotClass} ${animateClass}`;
        }
    }
}
