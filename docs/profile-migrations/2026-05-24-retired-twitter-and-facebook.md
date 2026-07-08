# Profil-Migrationen — 2026-05-24

Notizen zum Aufräumen stiller RSS.app-Profile.
Anlass: Reihe von Twitter- und Facebook-Konten, die in ihren letzten Posts
ausdrücklich Migration zu Bluesky/Mastodon/Fediverse angekündigt haben und
seitdem verstummt sind.

## Neu angelegte Nachfolger-Profile

Für jedes alte Profil wurde – sofern in der Abschiedsnachricht ein konkreter
Handle genannt wurde – das neue Profil hier in der App angelegt. Die alten
Profile bleiben unangetastet, damit historische Items erhalten bleiben.

| Neue ID | Netzwerk | URL | Ersetzt (alte Profil-ID) | Quelltext der Ankündigung |
|---:|---|---|---:|---|
| 2263 | bluesky_profile | `https://bsky.app/profile/criticalmassleipzig.de` | 1698 `CMLPZcriticalmassleipzig` (facebook_page) | „Wir verabschieden uns von Facebook. Ihr könnt uns weiterhin folgen auf Bluesky, Instagram, Telegram, Whatsapp." (2025-01-26) |
| 2264 | bluesky_profile | `https://bsky.app/profile/cmvie.bsky.social` | 136 `originalcmvie` (twitter) | „Wir sind nun drüben im blauen Himmel" (2025-03-20) |
| 2265 | bluesky_profile | `https://bsky.app/profile/kidicalmassmh.bsky.social` | 1620 `kidicalmassmh` (instagram_profile) | „Wir sind jetzt auch mit einem Account auf #Bluesky vertreten. Folgt uns dort!" (2025-09-15) |
| 2266 | bluesky_profile | `https://bsky.app/profile/criticalmassmh.bsky.social` | 1591 `criticalmassmuelheimruhr` (instagram_profile) | „Als Alternative zu Twitter (X) und anderen Sozialen Medien könnt ihr uns jetzt auch bei Bluesky folgen: CriticalMassMH.bsky.social" (2024-01-05) |
| 2267 | bluesky_profile | `https://bsky.app/profile/kidicalmasss.bsky.social` | 2215 `KidicalMass_S` (twitter) | „Ihr findet uns auch im Fediverse … Insta … bluesky … #byebyeElon" (2024-06-21) |
| 2268 | mastodon | `https://verkehrswende.social/@CritMassHann` | 2208 `critmasshann` (twitter) | „Mit X ist jetzt Schluss. Selten fällt ein Abschied leichter." (2025-11-28) |
| 2269 | mastodon | `https://verkehrswende.social/@KidicalMass_S` | 2215 `KidicalMass_S` (twitter) | s.o. (gleiche Ankündigung wie 2267) |

## Nicht angelegt

Schon vorhanden, nichts zu tun:
- `https://www.instagram.com/criticalmassleipzig/` (im Leipzig-Abschied erwähnt)
- `https://www.instagram.com/kidicalmass_s/` (im KidicalMass_S-Abschied erwähnt)

Mangels nennenswerter Quell-Handles in den Abschiedstexten nicht angelegt:
- 1498 `ClimaticalMass` (twitter) — Abschied 2020 ohne Nennung einer Nachfolge-Plattform
- 1711 `criticalmassdd` (twitter) — nur „Fediverse oder Mastodon" ohne konkreten Handle
- 2213 `cm_muenchen` (twitter) — nur „Wir sind bei Mastodon und anderen socials" ohne Handle

## RSS.app-Aufräumen (gleicher Zeitpunkt)

Beim selben Lauf wurden **131 RSS.app-Feeds gelöscht** — strikt nur bei RSS.app,
Profile in dieser Anwendung blieben unangetastet:

- 9 Feeds aus Gruppe A (die oben genannten Profile + ClimaticalMass / criticalmassdd / cm_muenchen)
- 123 Feeds aus Gruppe C: RSS.app-verknüpfte Profile, deren letzter Post ≥ 3 Jahre zurücklag (61 twitter, 32 instagram_profile, 30 facebook_page)
- 1 Überschneidung (1498 ClimaticalMass war in beiden Gruppen)

## Nachzieh-Aufräumen in der DB

Direkt im Anschluss wurde bei genau denselben 131 Profilen der
`rss_feed_id`-Eintrag aus `additional_data` per `JSON_REMOVE` entfernt.
Damit zeigen `/rssapp-profiles` und die RSS.app-Sync-Logik wieder einen
konsistenten Stand und der Cron läuft nicht mehr in 404 gegen tote
Feed-IDs. Profile, Items und alle übrigen Felder bleiben unverändert.

Vorher / nachher auf `/rssapp-profiles`: **465 → 334**.
