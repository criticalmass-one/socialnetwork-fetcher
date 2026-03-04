import { Controller } from '@hotwired/stimulus';
import DataTable from 'datatables.net-bs5';

export default class extends Controller {
    static values = {
        pageLength: { type: Number, default: 25 },
        order: { type: Array, default: [[0, 'asc']] },
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
        });
    }

    disconnect() {
        if (this._dt) {
            this._dt.destroy();
            this._dt = null;
        }
    }
}
