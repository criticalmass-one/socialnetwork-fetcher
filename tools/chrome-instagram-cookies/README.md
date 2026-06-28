# Instagram Cookies → feeds.maltehuebner.dev (Chrome-Extension)

Liest die Instagram-Session-Cookies aus dem Browser (inkl. der httpOnly-Cookies
wie `sessionid`, die ein normales Script **nicht** lesen kann) und lädt sie als
yt-dlp-`cookies.txt` (Netscape-Format) per `PUT /api/yt-dlp-cookies` in die
feeds-App. Dort schreibt der `CookieController` sie nach `YT_DLP_COOKIES_FILE`
(`…/feeds.maltehuebner.dev/var/yt-dlp-cookies.txt`). Danach kann yt-dlp die
Instagram-Medien herunterladen.

## Installation (entpackt, „Developer Mode")
1. Chrome → `chrome://extensions/`
2. Oben rechts **Entwicklermodus** aktivieren
3. **„Entpackte Erweiterung laden"** → diesen Ordner (`tools/chrome-instagram-cookies/`) wählen
4. Auf das Extension-Icon → **Einstellungen…** → Endpoint (Default passt) + **API-Token** (der feeds-Bearer-Token, gleich wie im WP-Instagram-Block) eintragen, speichern.

## Benutzung
1. In **diesem** Chrome bei `instagram.com` mit dem gewünschten Account **einloggen**.
2. Extension-Icon anklicken → **„Cookies an feeds senden"**.
3. Erfolgsmeldung „✓ N Cookies gespeichert".

## Hinweise
- **Sicherheit:** Die Cookies = aktive Instagram-Session. Übertragung nur per HTTPS,
  der Endpoint ist Bearer-Token-geschützt, die Datei wird mit `0600` geschrieben.
- **Account:** Möglichst ein separater Instagram-Account, nicht der Hauptaccount.
- **Ablauf:** Instagram-Cookies laufen nach Tagen/Wochen ab → bei Bedarf einfach
  erneut „senden" klicken (kein scp, kein Server-Login nötig).
- Kein Icon mitgeliefert — Chrome nutzt einen Platzhalter; optional eine
  `icon128.png` ergänzen und im `manifest.json` referenzieren.
