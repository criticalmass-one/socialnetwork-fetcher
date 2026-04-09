import { Controller } from '@hotwired/stimulus';
import DataTable from 'datatables.net-bs5';

export default class extends Controller {
    static values = {
        pageLength: { type: Number, default: 25 },
        order: { type: Array, default: [[0, 'asc']] },
        filterColumn: { type: Number, default: -1 },
        filterLabel: { type: String, default: 'Alle' },
    };

    connect() {
        if (DataTable.isDataTable(this.element)) {
            return;
        }

        this._dt = new DataTable(this.element, {
            pageLength: this.pageLengthValue,
            order: this.orderValue,
            language: {
                search: 'Suche:',
                lengthMenu: '_MENU_ Einträge pro Seite',
                info: '_START_ bis _END_ von _TOTAL_ Einträgen',
                infoEmpty: 'Keine Einträge vorhanden',
                infoFiltered: '(gefiltert aus _MAX_ Einträgen)',
                zeroRecords: 'Keine passenden Einträge gefunden',
                paginate: {
                    first: 'Erste',
                    last: 'Letzte',
                    next: 'Weiter',
                    previous: 'Zurück',
                },
            },
            columnDefs: [
                { orderable: false, targets: 'no-sort' },
            ],
            initComplete: () => {
                if (this.filterColumnValue >= 0) {
                    this._addColumnFilter();
                }
            },
        });
    }

    _addColumnFilter() {
        const column = this._dt.column(this.filterColumnValue);
        const wrapper = this._dt.table().container();
        const searchRow = wrapper.querySelector('.dt-search') || wrapper.querySelector('.dataTables_filter');

        const select = document.createElement('select');
        select.className = 'form-select form-select-sm d-inline-block w-auto me-3';

        const allOption = document.createElement('option');
        allOption.value = '';
        allOption.textContent = this.filterLabelValue;
        select.appendChild(allOption);

        const values = new Set();
        column.data().each(val => {
            const text = val.replace(/<[^>]*>/g, '').trim();
            if (text) values.add(text);
        });

        [...values].sort().forEach(val => {
            const option = document.createElement('option');
            option.value = val;
            option.textContent = val;
            select.appendChild(option);
        });

        select.addEventListener('change', () => {
            const search = select.value ? select.value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') : '';
            column.search(search, true, false).draw();
        });

        if (searchRow) {
            searchRow.prepend(select);
        }
    }

    disconnect() {
        if (this._dt) {
            this._dt.destroy();
            this._dt = null;
        }
    }
}
