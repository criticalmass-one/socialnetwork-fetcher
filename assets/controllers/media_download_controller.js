import { Controller } from '@hotwired/stimulus';
import { Modal } from 'bootstrap';

export default class extends Controller {
    static values = {
        url: String,
        token: String,
    };

    async download() {
        const modal = document.getElementById('mediaDownloadModal');
        const bsModal = Modal.getOrCreateInstance(modal);

        const body = modal.querySelector('.modal-body');
        body.innerHTML = this._renderLoading();
        modal.querySelector('.modal-footer').classList.add('d-none');
        bsModal.show();

        const formData = new FormData();
        formData.append('_token', this.tokenValue);

        try {
            const response = await window.fetch(this.urlValue, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            const data = await response.json();

            if (response.ok && data.success) {
                body.innerHTML = this._renderSuccess(data);
                this._updateMediaDisplay(data);
            } else {
                body.innerHTML = this._renderError(data.error || 'Unbekannter Fehler');
            }
        } catch (error) {
            body.innerHTML = this._renderError(error.message);
        }

        modal.querySelector('.modal-footer').classList.remove('d-none');
    }

    _renderLoading() {
        return `
            <div class="text-center py-4">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Laden...</span>
                </div>
                <p class="mb-0 text-muted">Medien werden heruntergeladen…</p>
            </div>
        `;
    }

    _renderSuccess(data) {
        let details = '';
        const photoPaths = data.photoPaths || [];

        if (photoPaths.length > 0) {
            details += `<tr><th>Fotos</th><td><i class="fas fa-check text-success me-1"></i>${photoPaths.length} Foto(s) heruntergeladen</td></tr>`;
        }

        if (data.videoPath) {
            details += `<tr><th>Video</th><td><i class="fas fa-check text-success me-1"></i>${this._escapeHtml(data.videoPath)}</td></tr>`;
        }

        if (photoPaths.length === 0 && !data.videoPath) {
            details += `<tr><td colspan="2" class="text-muted">Keine Medien zum Herunterladen gefunden.</td></tr>`;
        }

        return `
            <div class="py-2">
                <div class="alert alert-success mb-3">
                    <i class="fas fa-check-circle me-1"></i>Download abgeschlossen
                </div>
                <table class="table table-sm table-borderless mb-0">
                    ${details}
                </table>
            </div>
        `;
    }

    _renderError(message) {
        return `
            <div class="py-2">
                <div class="alert alert-danger mb-0">
                    <i class="fas fa-exclamation-triangle me-1"></i>${this._escapeHtml(message)}
                </div>
            </div>
        `;
    }

    _updateMediaDisplay(data) {
        const mediaCard = document.getElementById('media-card');
        const photoPaths = data.photoPaths || [];

        if (mediaCard && (photoPaths.length > 0 || data.videoPath)) {
            let html = '';

            if (photoPaths.length > 0) {
                const colClass = photoPaths.length === 1 ? 'col-12' : 'col-6';
                html += '<div class="row g-2 mb-3">';

                photoPaths.forEach((path, i) => {
                    html += `<div class="${colClass}"><img src="/media/${this._escapeHtml(path)}" class="img-fluid rounded" alt="Foto ${i + 1}"></div>`;
                });

                html += '</div>';
            }

            if (data.videoPath) {
                html += `<div class="mb-3"><video src="/media/${this._escapeHtml(data.videoPath)}" controls class="w-100 rounded"></video></div>`;
            }

            const content = mediaCard.querySelector('.media-content');

            if (content) {
                content.innerHTML = html;
            }
        }
    }

    _escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
