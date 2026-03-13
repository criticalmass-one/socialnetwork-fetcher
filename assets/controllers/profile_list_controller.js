import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        url: String,
        page: Number,
        pages: Number,
    };

    static targets = ['searchInput', 'networkCheckbox', 'networkCount', 'statusRadio', 'tableBody', 'pagination', 'totalBadge'];

    _searchTimeout = null;

    connect() {
        this.paginationTarget.addEventListener('click', this._onPaginationClick.bind(this));
    }

    disconnect() {
        this.paginationTarget.removeEventListener('click', this._onPaginationClick.bind(this));
        clearTimeout(this._searchTimeout);
    }

    onSearchInput() {
        clearTimeout(this._searchTimeout);
        this._searchTimeout = setTimeout(() => {
            this.pageValue = 1;
            this._load();
        }, 300);
    }

    onNetworkChange() {
        this._updateNetworkCount();
        this.pageValue = 1;
        this._load();
    }

    clearNetworkFilter() {
        this.networkCheckboxTargets.forEach(cb => cb.checked = false);
        this._updateNetworkCount();
        this.pageValue = 1;
        this._load();
    }

    onStatusChange() {
        this.pageValue = 1;
        this._load();
    }

    clearStatusFilter() {
        this.statusRadioTargets.forEach(r => r.checked = false);
        this.pageValue = 1;
        this._load();
    }

    _onPaginationClick(event) {
        const link = event.target.closest('a.page-link');
        if (!link) return;

        event.preventDefault();

        const url = new URL(link.href, window.location.origin);
        const page = parseInt(url.searchParams.get('page'), 10);
        if (page && page !== this.pageValue) {
            this.pageValue = page;
            this._load();
        }
    }

    async _load() {
        const params = new URLSearchParams();
        params.set('page', this.pageValue);

        const search = this.searchInputTarget.value.trim();
        if (search) {
            params.set('search', search);
        }

        this.networkCheckboxTargets.forEach(cb => {
            if (cb.checked) {
                params.append('networks[]', cb.value);
            }
        });

        const selectedStatus = this.statusRadioTargets.find(r => r.checked);
        if (selectedStatus) {
            params.set('status', selectedStatus.value);
        }

        try {
            const response = await fetch(`${this.urlValue}?${params}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!response.ok) return;

            const data = await response.json();

            this.tableBodyTarget.innerHTML = data.html;
            this.paginationTarget.innerHTML = data.paginationHtml;
            this.pageValue = data.page;
            this.pagesValue = data.pages;
            this.totalBadgeTarget.textContent = data.total;

            const url = new URL(window.location);
            url.search = params.toString();
            history.replaceState(null, '', url);
        } catch (error) {
            // silently fail on network errors
        }
    }

    _updateNetworkCount() {
        const checked = this.networkCheckboxTargets.filter(cb => cb.checked).length;
        this.networkCountTarget.textContent = checked > 0 ? checked : '';
    }
}
