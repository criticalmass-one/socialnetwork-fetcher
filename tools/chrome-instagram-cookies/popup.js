const DEFAULT_ENDPOINT = 'https://feeds.maltehuebner.dev/api/yt-dlp-cookies';

const statusEl = document.getElementById('status');
const sendBtn = document.getElementById('send');

document.getElementById('opts').addEventListener('click', (e) => {
  e.preventDefault();
  chrome.runtime.openOptionsPage();
});

function setStatus(msg, cls) {
  statusEl.textContent = msg;
  statusEl.className = cls || '';
}

// Wandelt ein chrome.cookies-Cookie in eine Netscape-cookies.txt-Zeile.
function toNetscapeLine(c) {
  const includeSub = c.domain.startsWith('.') ? 'TRUE' : 'FALSE';
  const secure = c.secure ? 'TRUE' : 'FALSE';
  // Session-Cookies (ohne expirationDate) -> 0
  const expiry = c.expirationDate ? Math.floor(c.expirationDate) : 0;
  const domainField = (c.httpOnly ? '#HttpOnly_' : '') + c.domain;
  return [domainField, includeSub, c.path || '/', secure, expiry, c.name, c.value].join('\t');
}

async function buildCookiesTxt() {
  // Alle instagram.com-Cookies (inkl. Subdomains).
  const cookies = await chrome.cookies.getAll({ domain: 'instagram.com' });
  if (!cookies || cookies.length === 0) {
    throw new Error('Keine Instagram-Cookies gefunden. Bist du in diesem Browser bei instagram.com eingeloggt?');
  }
  const hasSession = cookies.some((c) => c.name === 'sessionid' && c.value);
  const lines = cookies.map(toNetscapeLine);
  const txt = '# Netscape HTTP Cookie File\n' + lines.join('\n') + '\n';
  return { txt, count: cookies.length, hasSession };
}

sendBtn.addEventListener('click', async () => {
  sendBtn.disabled = true;
  setStatus('Lese Cookies …');
  try {
    const cfg = await chrome.storage.sync.get(['endpoint', 'token']);
    const endpoint = (cfg.endpoint || DEFAULT_ENDPOINT).trim();
    const token = (cfg.token || '').trim();
    if (!token) {
      setStatus('Kein API-Token gesetzt. Bitte unter „Einstellungen…" hinterlegen.', 'err');
      return;
    }

    const { txt, count, hasSession } = await buildCookiesTxt();
    if (!hasSession) {
      setStatus('Warnung: kein „sessionid"-Cookie gefunden – evtl. nicht eingeloggt. Sende trotzdem …');
    } else {
      setStatus(`Sende ${count} Cookies …`);
    }

    const res = await fetch(endpoint, {
      method: 'PUT',
      headers: {
        'Authorization': 'Bearer ' + token,
        'Content-Type': 'text/plain; charset=utf-8',
      },
      body: txt,
    });

    const bodyText = await res.text();
    if (!res.ok) {
      setStatus(`Fehler ${res.status}: ${bodyText}`, 'err');
      return;
    }
    let info = bodyText;
    try { const j = JSON.parse(bodyText); info = `${j.cookies} Cookies gespeichert (${j.bytes} Bytes).`; } catch (_) {}
    setStatus('✓ ' + info, 'ok');
  } catch (err) {
    setStatus('Fehler: ' + (err && err.message ? err.message : String(err)), 'err');
  } finally {
    sendBtn.disabled = false;
  }
});
