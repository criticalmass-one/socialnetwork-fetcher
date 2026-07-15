/*
 * Fetches the media bytes (using the browser's residential IP + Instagram
 * session via host permissions) and uploads them to the app's /media-upload
 * endpoint. Runs in the service worker so it survives the popup closing.
 */

const IG_APP_ID = '936619743392459';
const SC_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';

function shortcodeToMediaId(shortcode) {
    let id = 0n;
    for (const ch of shortcode) {
        const idx = SC_ALPHABET.indexOf(ch);
        if (idx < 0) return null;
        id = id * 64n + BigInt(idx);
    }
    return id.toString();
}

function mediaFromItem(item) {
    const nodes = item.carousel_media || [item];
    let videoUrl = null;
    let imageUrl = null;
    for (const n of nodes) {
        if (!videoUrl && n.video_versions?.length) {
            videoUrl = n.video_versions[0].url;
        }
        if (!imageUrl && n.image_versions2?.candidates?.length) {
            imageUrl = n.image_versions2.candidates[0].url;
        }
    }
    return { videoUrl, imageUrl };
}

// Reliable path: ask Instagram's own media API (from the browser session) for
// the direct CDN video/image URLs, instead of scraping the DOM/og: tags.
async function fetchViaApi(shortcode) {
    const mediaId = shortcodeToMediaId(shortcode);
    if (!mediaId) return {};

    const res = await fetch(`https://www.instagram.com/api/v1/media/${mediaId}/info/`, {
        headers: { 'X-IG-App-ID': IG_APP_ID, 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'include',
    });
    if (!res.ok) return {};

    const data = await res.json().catch(() => null);
    const item = data?.items?.[0];
    return item ? mediaFromItem(item) : {};
}

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

async function upload({ permalink, shortcode, videoUrl, imageUrl, text, dateTime, author }) {
    const { appUrl, token } = await getSettings();
    if (!token) {
        throw new Error('Kein Token gesetzt — bitte in den Optionen konfigurieren.');
    }

    // Prefer Instagram's API for direct CDN URLs; fall back to page-extracted ones.
    if (shortcode) {
        try {
            const api = await fetchViaApi(shortcode);
            if (api.videoUrl) videoUrl = api.videoUrl;
            if (api.imageUrl) imageUrl = api.imageUrl;
        } catch (e) {
            // keep the page-extracted URLs
        }
    }

    if (!videoUrl && !imageUrl) {
        throw new Error('Kein Medium gefunden (Instagram-API lieferte nichts).');
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
