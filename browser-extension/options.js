const appUrlEl = document.getElementById('appUrl');
const tokenEl = document.getElementById('token');
const savedEl = document.getElementById('saved');

chrome.storage.sync.get(['appUrl', 'token']).then(({ appUrl, token }) => {
    appUrlEl.value = appUrl || 'https://feeds.maltehuebner.dev';
    tokenEl.value = token || '';
});

document.getElementById('save').addEventListener('click', async () => {
    await chrome.storage.sync.set({
        appUrl: appUrlEl.value.trim().replace(/\/+$/, ''),
        token: tokenEl.value.trim(),
    });
    savedEl.hidden = false;
    setTimeout(() => { savedEl.hidden = true; }, 2000);
});
