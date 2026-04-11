import { Controller } from '@hotwired/stimulus';

/**
 * Route Picker — searchable checkbox selector with chip display.
 *
 * Targets:
 *   searchInput  – text input for filtering
 *   item         – each route checkbox wrapper (has data-route-name)
 *   chipZone     – container for selected-route chips
 *   count        – element showing "N routes selected"
 *   submitBtn    – the compare button (disabled when <2 selected)
 */
export default class extends Controller {
    static targets = ['searchInput', 'item', 'chipZone', 'count', 'submitBtn'];

    connect() {
        this.refresh();
    }

    /* ---------- actions ---------- */

    filter() {
        const q = this.searchInputTarget.value.toLowerCase().trim();
        this.itemTargets.forEach(el => {
            const name = (el.dataset.routeName || '').toLowerCase();
            el.classList.toggle('hidden', q !== '' && !name.includes(q));
        });
    }

    toggle() {
        this.refresh();
    }

    removeChip(e) {
        const id = e.currentTarget.dataset.routeId;
        const cb = this.element.querySelector(`input[type="checkbox"][value="${id}"]`);
        if (cb) { cb.checked = false; }
        this.refresh();
    }

    clearAll() {
        this.element.querySelectorAll('input[type="checkbox"]:checked').forEach(cb => {
            cb.checked = false;
        });
        this.refresh();
    }

    /* ---------- helpers ---------- */

    refresh() {
        const checked = [...this.element.querySelectorAll('input[type="checkbox"]:checked')];
        const n = checked.length;

        // Update count
        if (this.hasCountTarget) {
            this.countTarget.textContent = `${n} route${n !== 1 ? 's' : ''} selected`;
        }

        // Update submit button state
        if (this.hasSubmitBtnTarget) {
            const valid = n >= 2 && n <= 5;
            this.submitBtnTarget.disabled = !valid;
            this.submitBtnTarget.classList.toggle('opacity-50', !valid);
            this.submitBtnTarget.classList.toggle('cursor-not-allowed', !valid);
        }

        // Build chips using safe DOM methods
        if (this.hasChipZoneTarget) {
            this.chipZoneTarget.replaceChildren();

            checked.forEach(cb => {
                const colour = cb.dataset.colour || '6b7280';
                const label = cb.dataset.label || cb.value;

                const chip = document.createElement('span');
                chip.className = 'inline-flex items-center gap-1 pl-2.5 pr-1.5 py-1 rounded-full text-xs font-semibold cursor-default transition-all';
                chip.style.cssText = `background-color:#${colour}18;color:#${colour};border:1px solid #${colour}30;`;

                const labelText = document.createTextNode(label);
                chip.appendChild(labelText);

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.dataset.action = 'route-picker#removeChip';
                removeBtn.dataset.routeId = cb.value;
                removeBtn.className = 'ml-0.5 rounded-full p-0.5 hover:bg-black/10 transition-colors';

                const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                svg.setAttribute('class', 'w-3 h-3');
                svg.setAttribute('fill', 'none');
                svg.setAttribute('stroke', 'currentColor');
                svg.setAttribute('viewBox', '0 0 24 24');

                const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                path.setAttribute('stroke-linecap', 'round');
                path.setAttribute('stroke-linejoin', 'round');
                path.setAttribute('stroke-width', '2.5');
                path.setAttribute('d', 'M6 18L18 6M6 6l12 12');
                svg.appendChild(path);
                removeBtn.appendChild(svg);

                chip.appendChild(removeBtn);
                this.chipZoneTarget.appendChild(chip);
            });

            // Show/hide clear-all
            const clearBtn = this.chipZoneTarget.parentElement.querySelector('[data-action="route-picker#clearAll"]');
            if (clearBtn) { clearBtn.classList.toggle('hidden', n === 0); }
        }
    }
}
