import { Controller } from '@hotwired/stimulus';
import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.bootstrap5.min.css';

/*
 * Turns a native (multi-)select into a searchable, tag-based widget.
 * Attach with data-controller="searchable-select" on a <select> element;
 * the underlying select keeps its value, so normal form submission is
 * unchanged. Optional data-searchable-select-placeholder-value overrides
 * the placeholder text.
 */
export default class extends Controller {
    static values = {
        placeholder: { type: String, default: 'Tippen zum Suchen …' },
        searchFields: { type: Array, default: ['text'] },
    };

    connect() {
        const isMultiple = this.element.multiple;

        this.select = new TomSelect(this.element, {
            plugins: isMultiple ? ['remove_button'] : [],
            placeholder: this.placeholderValue,
            maxOptions: null,
            maxItems: isMultiple ? null : 1,
            hidePlaceholder: false,
            // Match against the visible label plus any per-option data-*
            // fields (e.g. title, identifier) exposed as Tom Select option data.
            searchField: this.searchFieldsValue,
            sortField: [{ field: 'text', direction: 'asc' }],
        });
    }

    disconnect() {
        if (this.select) {
            this.select.destroy();
            this.select = null;
        }
    }
}
