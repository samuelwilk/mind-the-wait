import { Controller } from '@hotwired/stimulus';

/**
 * Mobile menu toggle controller
 */
export default class extends Controller {
    static targets = ['menu'];

    toggle() {
        this.menuTarget.classList.toggle('hidden');
    }
}
