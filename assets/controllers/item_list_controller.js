import { Controller } from '@hotwired/stimulus';
import Handlebars from 'handlebars';

export default class extends Controller {
    static values = {
        url: String,
        page: Number,
        pages: Number,
        profile: Number,
    };

    static targets = [
        'searchInput', 'profileSelect', 'networkCheckbox', 'networkCount',
        'statusRadio', 'tableBody', 'pagination', 'totalBadge',
        'itemRowTemplate', 'emptyTemplate', 'paginationTemplate',
    ];

    _searchTimeout = null;
    _itemRowTpl = null;
    _emptyTpl = null;
    _paginationTpl = null;

    connect() {
        this._itemRowTpl = Handlebars.compile(this.itemRowTemplateTarget.innerHTML);
        this._emptyTpl = Handlebars.compile(this.emptyTemplateTarget.innerHTML);
        this._paginationTpl = Handlebars.compile(this.paginationTemplateTarget.innerHTML);

        Handlebars.registerHelper('truncate', (str, len) => {
            if (!str) return '';
            return str.length > len ? str.substring(0, len) + '...' : str;
        });

        Handlebars.registerHelper('eq', (a, b) => a === b);
        Handlebars.registerHelper('gt', (a, b) => a > b);

        if (this.profileValue) {
            this._load();
        }
    }

    disconnect() {
        clearTimeout(this._searchTimeout);
    }

    onSearchInput() {
        clearTimeout(this._searchTimeout);
        this._searchTimeout = setTimeout(() => {
            this.pageValue = 1;
            this._load();
        }, 300);
    }

    onProfileChange() {
        this.pageValue = 1;
        this._load();
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

    onPageClick(event) {
        event.preventDefault();
        const page = parseInt(event.currentTarget.dataset.page, 10);
        if (page && page !== this.pageValue) {
            this.pageValue = page;
            this._load();
        }
    }

    async _load() {
        const params = new URLSearchParams();
        params.set('page', this.pageValue);

        if (this.hasSearchInputTarget) {
            const search = this.searchInputTarget.value.trim();
            if (search) params.set('search', search);
        }

        if (this.hasProfileSelectTarget) {
            const profileId = this.profileSelectTarget.value;
            if (profileId) params.set('profile', profileId);
        } else if (this.profileValue) {
            params.set('profile', this.profileValue);
        }

        if (this.hasNetworkCheckboxTarget) {
            this.networkCheckboxTargets.forEach(cb => {
                if (cb.checked) params.append('networks[]', cb.value);
            });
        }

        if (this.hasStatusRadioTarget) {
            const selected = this.statusRadioTargets.find(r => r.checked);
            if (selected) params.set('status', selected.value);
        }

        try {
            const response = await fetch(`${this.urlValue}?${params}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!response.ok) return;

            const data = await response.json();

            if (data.items.length === 0) {
                this.tableBodyTarget.innerHTML = this._emptyTpl();
            } else {
                this.tableBodyTarget.innerHTML = data.items
                    .map(item => this._itemRowTpl({ ...item, csrfToken: data.csrfToken }))
                    .join('');
            }

            this._renderPagination(data.page, data.pages);
            this.pageValue = data.page;
            this.pagesValue = data.pages;

            if (this.hasTotalBadgeTarget) {
                this.totalBadgeTarget.textContent = data.total;
            }

            if (!this.profileValue) {
                const url = new URL(window.location);
                url.search = params.toString();
                history.replaceState(null, '', url);
            }
        } catch (error) {
            // silently fail on network errors
        }
    }

    _renderPagination(page, pages) {
        if (!this.hasPaginationTarget) return;

        if (pages <= 1) {
            this.paginationTarget.innerHTML = '';
            return;
        }

        const pageNumbers = [];
        for (let p = 1; p <= pages; p++) {
            if (p <= 3 || p > pages - 2 || (p >= page - 1 && p <= page + 1)) {
                pageNumbers.push({ num: p, active: p === page, ellipsis: false });
            } else if (p === 4 || p === pages - 2) {
                pageNumbers.push({ num: p, active: false, ellipsis: true });
            }
        }

        this.paginationTarget.innerHTML = this._paginationTpl({
            page,
            pages,
            prevDisabled: page <= 1,
            nextDisabled: page >= pages,
            prevPage: page - 1,
            nextPage: page + 1,
            pageNumbers,
        });
    }

    _updateNetworkCount() {
        if (!this.hasNetworkCountTarget) return;
        const checked = this.networkCheckboxTargets.filter(cb => cb.checked).length;
        this.networkCountTarget.textContent = checked > 0 ? checked : '';
    }
}
