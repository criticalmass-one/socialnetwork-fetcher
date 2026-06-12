import { Controller } from '@hotwired/stimulus';

/**
 * Searchable profile picker on the group show page.
 *
 * Live-searches profiles via the JSON endpoint and renders them as
 * checkboxes inside the surrounding form. Adding members is a regular
 * form POST to app_group_profile_add — JS is only used for searching.
 */
export default class extends Controller {
    static targets = ['input', 'results', 'submit'];
    static values = {
        searchUrl: String,
    };

    connect() {
        this.timeout = null;
        this.search();
    }

    disconnect() {
        clearTimeout(this.timeout);
    }

    onInput() {
        clearTimeout(this.timeout);
        this.timeout = setTimeout(() => this.search(), 300);
    }

    async search() {
        const term = this.inputTarget.value.trim();
        this.resultsTarget.innerHTML = '<div class="text-muted small py-2">Suche…</div>';

        try {
            const url = new URL(this.searchUrlValue, window.location.origin);
            if (term !== '') {
                url.searchParams.set('q', term);
            }

            const response = await window.fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            this._renderResults(data.results, data.limit, term);
        } catch (error) {
            this.resultsTarget.innerHTML = `<div class="text-danger small py-2">Suche fehlgeschlagen: ${this._escape(error.message)}</div>`;
        }

        this._updateSubmitState();
    }

    onToggle() {
        this._updateSubmitState();
    }

    _renderResults(results, limit, term) {
        if (results.length === 0) {
            this.resultsTarget.innerHTML = term === ''
                ? '<div class="text-muted small py-2">Keine weiteren Profile verfügbar.</div>'
                : '<div class="text-muted small py-2">Keine Treffer. Suche nach Identifier oder Titel.</div>';
            return;
        }

        const rows = results.map((p) => `
            <label class="d-flex align-items-center gap-2 py-1 border-bottom picker-row" style="cursor: pointer;">
                <input type="checkbox" class="form-check-input mt-0 flex-shrink-0" name="profileIds[]" value="${p.id}"
                       data-action="change->group-profile-picker#onToggle">
                ${p.network ? `<span class="net-badge flex-shrink-0" style="background-color: ${this._escape(p.networkBackgroundColor || '#666')}; color: ${this._escape(p.networkTextColor || '#fff')};"><i class="${this._escape(p.networkIcon || '')} me-1"></i>${this._escape(p.network)}</span>` : ''}
                <span class="text-truncate" title="${this._escape(p.identifier || '')}">${this._escape(p.label)}</span>
            </label>
        `).join('');

        const hint = results.length >= limit
            ? `<div class="text-muted small py-1">Es werden die ersten ${limit} Treffer angezeigt — Suche verfeinern für mehr.</div>`
            : '';

        this.resultsTarget.innerHTML = rows + hint;
    }

    _updateSubmitState() {
        const checked = this.resultsTarget.querySelectorAll('input[name="profileIds[]"]:checked').length;
        this.submitTarget.disabled = checked === 0;
        this.submitTarget.textContent = checked > 0
            ? `${checked} Profil${checked === 1 ? '' : 'e'} hinzufügen`
            : 'Profile hinzufügen';
    }

    _escape(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }
}
