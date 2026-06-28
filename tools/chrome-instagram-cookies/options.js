const DEFAULT_ENDPOINT = 'https://feeds.maltehuebner.dev/api/yt-dlp-cookies';

const endpointEl = document.getElementById('endpoint');
const tokenEl = document.getElementById('token');
const savedEl = document.getElementById('saved');

chrome.storage.sync.get(['endpoint', 'token']).then((cfg) => {
  endpointEl.value = cfg.endpoint || DEFAULT_ENDPOINT;
  tokenEl.value = cfg.token || '';
});

document.getElementById('save').addEventListener('click', async () => {
  await chrome.storage.sync.set({
    endpoint: (endpointEl.value || DEFAULT_ENDPOINT).trim(),
    token: tokenEl.value.trim(),
  });
  savedEl.textContent = '✓ gespeichert';
  setTimeout(() => { savedEl.textContent = ''; }, 2000);
});
