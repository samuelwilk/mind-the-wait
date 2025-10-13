import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

/**
 * Polls a Live Component at a regular interval
 *
 * Usage:
 *   <div data-controller="live-poll" data-live-poll-interval-value="30000">
 *     <twig:LiveComponent />
 *   </div>
 */
export default class extends Controller {
    static values = {
        interval: { type: Number, default: 30000 } // Default 30 seconds
    };

    connect() {
        this.startPolling();
    }

    disconnect() {
        this.stopPolling();
    }

    startPolling() {
        // Initial render is already done by Live Component
        // Start polling after the interval
        this.pollTimer = setInterval(() => {
            this.refresh();
        }, this.intervalValue);
    }

    stopPolling() {
        if (this.pollTimer) {
            clearInterval(this.pollTimer);
            this.pollTimer = null;
        }
    }

    async refresh() {
        // Find the Live Component element
        const liveElement = this.element.querySelector('[data-controller~="live"]');
        if (liveElement) {
            try {
                // Get the Live Component instance and trigger a re-render
                const component = await getComponent(liveElement);
                component.render();
            } catch (error) {
                console.error('Failed to refresh Live Component:', error);
            }
        }
    }
}
