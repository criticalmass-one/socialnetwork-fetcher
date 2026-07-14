# Feed Media Uploader (Chrome-Erweiterung)

Lädt Video/Fotos eines offenen Instagram-Posts in die Social-Network-Fetcher-App.
Der Server kann Instagram-Medien selbst nicht laden (Instagram blockt
Rechenzentrums-IPs); dein Browser hat eine Wohn-IP + eingeloggte Session und kommt
dran. Die Erweiterung greift das Medium ab und schickt es an den
`/media-upload`-Endpunkt der App, der es dem passenden Item (per Permalink)
zuordnet und – bei Videos – die Transkription anstößt.

## Voraussetzung: Token auf dem Server

In der Server-`.env.local` einen `MEDIA_UPLOAD_TOKEN` setzen (starker Zufallswert),
z. B.:

```
MEDIA_UPLOAD_TOKEN=<langer-zufallswert>
```

Danach `bin/console cache:clear --env=prod`. Leerer Token = Endpunkt deaktiviert.

## Installation

1. `chrome://extensions` öffnen.
2. **Entwicklermodus** oben rechts aktivieren.
3. **Entpackte Erweiterung laden** → diesen `browser-extension/`-Ordner wählen.
4. Auf das Erweiterungs-Icon → **Optionen** (bzw. Rechtsklick → Optionen):
   - **App-URL**: `https://feeds.maltehuebner.dev`
   - **Upload-Token**: derselbe Wert wie `MEDIA_UPLOAD_TOKEN` auf dem Server.
   - Speichern.

## Nutzung

1. Instagram-Post im Browser öffnen (URL der Form `…/p/…` oder `…/reel/…`).
   Wichtig: die **Einzelpost-Seite**, nicht der Feed.
2. Auf das Erweiterungs-Icon klicken → **Laden & hochladen**.
3. Eine Benachrichtigung meldet Erfolg (Video/Fotos, ob Transkription eingereiht).

## Grenzen (ehrlich)

- Instagram liefert Videos teils nur als segmentierten `blob:`-Stream; solche
  lassen sich nicht direkt herunterladen — die Erweiterung meldet dann „kein
  ladbares Medium gefunden". Sie nutzt vorrangig die `og:video`/`og:image`-Meta-Tags
  der Post-Seite, die meist die direkte CDN-URL enthalten.
- Karussells (mehrere Bilder) laden aktuell nur das Hauptbild.
- Instagram ändert seine Seiten häufig; die Extraktion kann brechen und
  gelegentlich Anpassung brauchen.
