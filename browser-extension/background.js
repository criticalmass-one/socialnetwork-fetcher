/*
 * Fetches the media bytes (using the browser's residential IP + Instagram
 * session via host permissions) and uploads them to the app's /media-upload
 * endpoint. Runs in the service worker so it survives the popup closing.
 */

async function getSettings() {
    const { appUrl, token } = await chrome.storage.sync.get(['appUrl', 'token']);
    return {
        appUrl: (appUrl || 'https://feeds.maltehuebner.dev').replace(/\/+$/, ''),
        token: token || '',
    };
}

async function fetchAsFile(url, fallbackName) {
    const res = await fetch(url, { credentials: 'include' });
    if (!res.ok) {
        throw new Error(`Download fehlgeschlagen (${res.status})`);
    }
    const blob = await res.blob();
    const ext = (blob.type.split('/')[1] || '').split(';')[0] || fallbackName.split('.').pop();
    const name = fallbackName.replace(/\.[^.]*$/, '') + '.' + ext;
    return new File([blob], name, { type: blob.type });
}

async function upload({ permalink, videoUrl, imageUrl, text, dateTime, author }) {
    const { appUrl, token } = await getSettings();
    if (!token) {
        throw new Error('Kein Token gesetzt — bitte in den Optionen konfigurieren.');
    }

    const form = new FormData();
    form.append('permalink', permalink);
    if (text) form.append('text', text);
    if (dateTime) form.append('dateTime', dateTime);
    if (author) form.append('author', author);

    if (videoUrl) {
        form.append('video', await fetchAsFile(videoUrl, 'video.mp4'));
    }
    if (imageUrl) {
        form.append('photos[]', await fetchAsFile(imageUrl, 'photo.jpg'));
    }

    const res = await fetch(`${appUrl}/media-upload`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${token}` },
        body: form,
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        throw new Error(data.error || `Upload fehlgeschlagen (${res.status})`);
    }
    return data;
}

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    if (message.type !== 'upload') {
        return false;
    }

    upload(message.payload)
        .then((data) => {
            notify('Hochgeladen', `${data.created ? 'Item angelegt. ' : ''}Video: ${data.video ? 'ja' : 'nein'}, Fotos: ${data.photos}${data.transcriptQueued ? ' — Transkription eingereiht' : ''}`);
            sendResponse({ ok: true, data });
        })
        .catch((err) => {
            notify('Fehler', err.message);
            sendResponse({ ok: false, error: err.message });
        });

    return true; // keep the message channel open for the async response
});

// 1x1 transparent PNG — avoids shipping a binary icon asset just for notifications.
const ICON = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

function notify(title, message) {
    chrome.notifications.create({
        type: 'basic',
        iconUrl: ICON,
        title,
        message,
    });
}
