/* Popup: detect the Instagram post in the active tab, extract its media URLs
 * in-page, and hand them to the background worker for download + upload. */

const goBtn = document.getElementById('go');
const statusEl = document.getElementById('status');
const permalinkEl = document.getElementById('permalink');
const notReadyEl = document.getElementById('notReady');

document.getElementById('opts').addEventListener('click', (e) => {
    e.preventDefault();
    chrome.runtime.openOptionsPage();
});

// Injected into the page: read the post's canonical URL and media via og: tags.
function extractMedia() {
    const canonical = document.querySelector('link[rel="canonical"]')?.href || location.href;
    const permalink = canonical.split('?')[0].split('#')[0];
    const og = (p) => document.querySelector(`meta[property="${p}"]`)?.content || null;

    let videoUrl = og('og:video:secure_url') || og('og:video');
    if (!videoUrl) {
        const v = document.querySelector('video');
        const src = v && (v.currentSrc || v.src);
        if (src && !src.startsWith('blob:')) videoUrl = src;
    }
    const imageUrl = og('og:image');

    return { permalink, videoUrl: videoUrl || null, imageUrl: imageUrl || null };
}

async function init() {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
    const isPost = tab?.url && /^https:\/\/www\.instagram\.com\/(p|reel)\//.test(tab.url);

    const { token } = await chrome.storage.sync.get(['token']);
    if (!token) {
        notReadyEl.hidden = false;
        notReadyEl.innerHTML = 'Kein Token gesetzt. Bitte zuerst in den <a href="#" id="o2">Einstellungen</a> hinterlegen.';
        document.getElementById('o2').addEventListener('click', (e) => { e.preventDefault(); chrome.runtime.openOptionsPage(); });
        return;
    }

    if (!isPost) {
        notReadyEl.hidden = false;
        notReadyEl.textContent = 'Öffne einen Instagram-Post (…/p/… oder …/reel/…) und klicke dann hier.';
        return;
    }

    permalinkEl.textContent = tab.url.split('?')[0];
    goBtn.disabled = false;
    goBtn.addEventListener('click', () => run(tab.id));
}

async function run(tabId) {
    goBtn.disabled = true;
    setStatus('Medien werden ausgelesen …', 'muted');

    try {
        const [{ result }] = await chrome.scripting.executeScript({
            target: { tabId },
            func: extractMedia,
        });

        if (!result || (!result.videoUrl && !result.imageUrl)) {
            setStatus('Kein ladbares Medium gefunden. Instagram liefert das Video evtl. nur als Stream (blob:).', 'err');
            goBtn.disabled = false;
            return;
        }

        setStatus('Hochladen …', 'muted');
        const res = await chrome.runtime.sendMessage({ type: 'upload', payload: result });

        if (res?.ok) {
            setStatus(`Fertig ✓ Video: ${res.data.video ? 'ja' : 'nein'}, Fotos: ${res.data.photos}` + (res.data.transcriptQueued ? '\nTranskription eingereiht.' : ''), 'ok');
        } else {
            setStatus('Fehler: ' + (res?.error || 'unbekannt'), 'err');
            goBtn.disabled = false;
        }
    } catch (e) {
        setStatus('Fehler: ' + e.message, 'err');
        goBtn.disabled = false;
    }
}

function setStatus(text, cls) {
    statusEl.textContent = text;
    statusEl.className = cls;
}

init();
