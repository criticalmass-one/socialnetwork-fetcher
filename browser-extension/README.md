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

## Wie die Medien geholt werden

Die Erweiterung fragt Instagrams eigene Media-API aus deiner Browser-Session ab
(`api/v1/media/{id}/info/`, Media-ID aus dem Shortcode berechnet) und bekommt so
die **direkten CDN-URLs** für Video und Bild — unabhängig vom `blob:`-Player.
Fällt die API aus, greift ein Fallback auf `og:video`/`og:image` und das
Seiten-JSON.

## Grenzen (ehrlich)

- Karussells (mehrere Medien) laden aktuell das erste Video bzw. das erste Bild.
- Instagram ändert seine API/Seiten gelegentlich; dann kann die Extraktion
  brechen und braucht Anpassung.
