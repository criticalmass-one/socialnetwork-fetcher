import { Controller } from '@hotwired/stimulus';
import DataTable from 'datatables.net-bs5';

export default class extends Controller {
    connect() {
        this.dt = new DataTable(this.element, {
            paging: true,
            pageLength: 50,
            lengthMenu: [25, 50, 100, 200],
            searching: true,
            order: [[4, 'desc']],
            language: {
                search: 'Suche:',
                lengthMenu: '_MENU_ Einträge anzeigen',
                info: '_START_–_END_ von _TOTAL_ Einträgen',
                infoEmpty: 'Keine Einträge',
                infoFiltered: '(gefiltert aus _MAX_)',
                paginate: { first: 'Erste', last: 'Letzte', next: 'Weiter', previous: 'Zurück' },
                emptyTable: 'Keine Profile mit RSS.app-Verknüpfung vorhanden.',
                zeroRecords: 'Keine passenden Einträge gefunden.',
            },
        });
    }

    disconnect() {
        if (this.dt) {
            this.dt.destroy();
            this.dt = null;
        }
    }
}
