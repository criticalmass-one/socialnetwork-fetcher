# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Symfony 8 application that fetches social media feeds from various networks (Mastodon, Bluesky, Instagram, Facebook, Threads, Homepage/RSS). Supports multi-tenancy: API clients authenticate via Bearer token and see only their linked profiles. Console commands for feed fetching, Web-UI for administration, REST API (API Platform) for client access.

Can run in two modes:
- **Upstream mode**: Fetches profiles from and pushes items to the criticalmass.in API
- **Standalone mode**: Imports data from criticalmass.in into a local PostgreSQL database, served via Web-UI and REST API

## Commands

```bash
bin/phpunit                                          # run all tests
bin/phpunit --filter testParseHandleFormat            # run single test
bin/phpunit tests/NetworkFeedFetcher/Bluesky/         # run test directory

php bin/console fetch-feed mastodon -c 50              # fetch feeds (actual command name)
php bin/console fetch-feed bluesky_profile --count=100 # fetch specific network
php bin/console feed:list instagram_profile           # list profiles
php bin/console network:list                          # list registered fetchers
php bin/console app:fetch-scheduled                   # run scheduled fetches (cron-based)
php bin/console app:import-profiles                   # import profiles from criticalmass.in API
php bin/console app:import-items -v                   # import items from criticalmass.in API
php bin/console app:import-items --network=twitter    # import only one network
php bin/console app:import-items --dry-run            # preview without writing

php bin/console app:client:create <name>              # create API client, outputs token
php bin/console app:client:list                       # list all clients
php bin/console app:client:regenerate-token <name>    # regenerate token
php bin/console app:client:disable <name>             # disable client
php bin/console app:client:enable <name>              # enable client

php bin/console app:rssapp:sync-feed-ids              # sync RSS.app feed IDs to DB
php bin/console app:rssapp:sync-feed-ids --dry-run    # preview without writing
php bin/console app:rssapp:sync-feed-ids --network=instagram_profile  # only one network
php bin/console app:rssapp:sync-feed-ids --force      # re-check profiles with existing feed IDs
php bin/console app:rssapp:sync-feed-ids -v           # show all profiles including skipped

php bin/console app:download-media                    # download media for all enabled profiles
php bin/console app:download-media --profile=42       # download for specific profile
php bin/console app:download-media --pending          # process items queued via the API (mediaStatus=pending)
php bin/console app:download-media --retry-failed     # retry previously failed downloads
php bin/console app:download-media --photos-only      # only photos
php bin/console app:download-media --videos-only      # only videos

php bin/console app:transcribe                        # transcribe videos for all profiles with transcribeVideos enabled
php bin/console app:transcribe --profile-id=42        # transcribe a specific profile's videos
php bin/console app:transcribe --item-id=99           # transcribe a single item's video
php bin/console app:transcribe --pending              # process items queued via the API/fetch (transcriptStatus=pending)
php bin/console app:transcribe --retry-failed         # retry previously failed transcriptions
```

## Architecture

### Feed Fetching Flow

```
Command → FeedFetcher → ProfileFetcher (loads profiles)
                      → NetworkFeedFetcher.fetch() (per profile, network-specific)
                      → FeedItemPersister (persists items)
                      → ProfilePersister (updates profile metadata + fetch timestamps)
                      → MediaDownloadService (downloads photos/videos if profile flags set)
```

After each fetch, `FeedFetcher` updates `lastFetchSuccessDateTime` / `lastFetchFailureDateTime` / `lastFetchFailureError` on the profile. Errors caught via exception or `markAsFailed()` (e.g. invalid identifier) are both persisted.

### Service Wiring

Network fetchers are auto-discovered: any class implementing `NetworkFeedFetcherInterface` gets tagged via `Kernel::build()` and injected into `FeedFetcher` via `SocialNetworkFetcherPass` compiler pass. No manual registration needed.

### Network Fetcher Types

- **Direct API fetchers**: `MastodonFeedFetcher`, `BlueskyFeedFetcher`, `HomepageFeedFetcher` — each extends `AbstractNetworkFeedFetcher` and calls the network's API directly
- **RSS.app-based fetchers**: `FacebookFeedFetcher`, `InstagramFeedFetcher`, `ThreadFeedFetcher` — extend `RssApp\Fetcher` (abstract), which proxies through RSS.app's API

### Each Network Fetcher Directory Contains

- `*FeedFetcher.php` — implements `NetworkFeedFetcherInterface`, orchestrates fetching
- `IdentifierParser.php` — extracts handle/username from URL-based identifiers stored in the DB
- `EntryConverter.php` — converts API response to `SocialNetworkFeedItem`

### Entities

- **Profile** — Social network profile: `id`, `identifier` (URL), `title` (optional display name), `network` (FK), `autoFetch`, `fetchSource`, `savePhotos`, `saveVideos`, `transcribeVideos`, `additionalData` (JSON), `deleted`/`deletedAt` (soft delete). `getDisplayName()` returns `title` if set, otherwise `identifier`.
- **Item** — Feed item: `id`, `profile` (FK), `uniqueIdentifier`, `text`, `title`, `dateTime`, `permalink`, `raw`, `hidden`, `deleted`, `photoPaths` (JSON array), `videoPath`, `mediaStatus`, `mediaError`, `transcript` (text), `transcriptStatus`, `transcriptError`. Supports soft-delete, hide toggles, media downloads, and video transcription.
- **Network** — Social network definition: `id`, `identifier`, `name`, `icon`, `backgroundColor`, `textColor`, `cronExpression`.
- **Client** — API client: `id`, `name`, `token`, `enabled`, `profiles` (ManyToMany via `client_profile` join table), `createdAt`.
- **Group** — Named bundle of a client's profiles (table `profile_group`, unique `(client_id, name)`): `id`, `name`, `description`, `color`, `client` (ManyToOne, NOT NULL — a group belongs to exactly one client, unlike shared profiles), `profiles` (ManyToMany via `profile_group_profile`), `createdAt`. Also carries the **public page** config: `publicPageEnabled`, `publicSlug` (unique), `publicPasswordHash`, `publicTitle`, `publicDescription`, `showPhotos`, `showVideos`, `showTranscript`, `showCaptions`, `timeWindowDays`. Serialization groups `group:read` (incl. `profileCount`, `publicUrl`, `publicPasswordProtected`), `group:detail` (embeds `profiles`), `group:write` (incl. write-only `publicPassword`). Used to read a combined feed per group. See "Public Group Page" below.

### Media Download System

Profiles can opt into automatic photo/video downloads via `savePhotos` and `saveVideos` flags. Media is stored via Flysystem (local adapter at `public/media/`).

- **`MediaUrlExtractor`** — extracts photo URLs from `raw` JSON (RSS.app `description_html` img tags, RSS.app `thumbnail` fallback, Bluesky `post.embed` images, Mastodon `media_attachments[]`). Supports multiple photos per item. Video URL is the item's `permalink` — for Mastodon/Bluesky only when the raw payload actually contains a video attachment/embed (posts without video are skipped instead of failing in yt-dlp); all other networks are probed blindly.
- **`PhotoDownloader`** — downloads images via HttpClient, stores as `{profileId}/{itemId}/photo_{index}.{ext}` in Flysystem
- **`YtDlpPhotoDownloader`** — extracts photos (including carousel/album images) via `yt-dlp` in original quality. Used for Instagram, Threads, and Facebook where RSS.app only provides a single thumbnail. Falls back to `PhotoDownloader` if `yt-dlp` is unavailable or returns no results.
- **`VideoDownloader`** — downloads videos via `yt-dlp` process, stores as `{profileId}/{itemId}/video.{ext}` on disk. Requires `yt-dlp` to be installed; gracefully skips if unavailable.
- **`MediaDownloadService`** — orchestrates downloads, manages `mediaStatus` lifecycle (`pending` → `downloading` → `completed`/`failed`). Selects download strategy per network: `yt-dlp` for RSS.app-based networks (Instagram, Threads, Facebook), direct URL download for Bluesky/Mastodon. `queueItem()`/`queueProfile()` mark items `pending` (used by the API trigger); `downloadPendingItems()` processes the queue.
- **`DownloadMediaCommand`** — CLI for bulk downloads with `--profile-id`, `--pending`, `--retry-failed`, `--photos-only`, `--videos-only` options. `--pending` drains the API-queued items.
- Auto-download triggers after feed fetch when profile has `savePhotos`/`saveVideos` enabled
- Manual download via button on item detail page (Stimulus `media_download_controller`)
- **API-triggered (re)download**: `POST /api/items/{id}/download-media` and `POST /api/profiles/{id}/download-media` (`?force=true` to re-queue all) mark items `pending` via `ItemMediaDownloadProcessor`/`ProfileMediaDownloadProcessor` (client-scoped, 202, require `savePhotos`/`saveVideos`); the actual download runs out-of-band via the `app:download-media --pending` cron. No Messenger — the queue is the `mediaStatus=pending` column.
- Item entity stores `photoPaths` (JSON array of relative paths), `videoPath` (string), `mediaStatus`, `mediaError`

### Video Transcription System

Profiles can opt into automatic transcription of downloaded videos via the `transcribeVideos` flag (only meaningful together with `saveVideos` — a video must be downloaded first). Transcription runs local via whisper.cpp, decoupled from the fetch/download run through a `transcriptStatus` queue (`null` → `pending` → `running` → `completed`/`failed`), mirroring the media-download queue.

- **`WhisperTranscriber`** (`src/Transcription/`) — low-level: extracts a 16 kHz mono WAV from the downloaded video with `ffmpeg`, then runs the whisper.cpp CLI (`-otxt`) and returns the transcript text. `isAvailable()` gracefully reports false unless the whisper binary, the model file and `ffmpeg` are all present. Configured via `$whisperCliPath`/`$whisperModelPath`/`$whisperLanguage` binds.
- **`TranscriptionService`** (`src/Transcription/`) — orchestrates the status lifecycle and persistence. `transcribe(Item)` does the work; `queueItem()`/`queueProfile()` mark items `pending` (used by the API trigger); `transcribePendingItems()` drains the queue.
- **Auto-queue**: after a video is downloaded, `MediaDownloadService::downloadMedia()` marks the item `transcriptStatus=pending` when the profile has `transcribeVideos` enabled. The actual transcription runs out-of-band via `app:transcribe --pending` (cron). No Messenger — the queue is the `transcriptStatus=pending` column, same pattern as media downloads.
- **`TranscribeCommand`** (`app:transcribe`) — CLI for bulk/targeted transcription with `--profile-id`, `--item-id`, `--pending`, `--retry-failed`. Backfills videos downloaded before the flag was enabled. Errors out cleanly if whisper.cpp/ffmpeg are unavailable.
- **API-triggered (re)transcription**: `POST /api/items/{id}/transcribe` and `POST /api/profiles/{id}/transcribe` (`?force=true` to re-queue all) mark items `pending` via `ItemTranscriptionProcessor`/`ProfileTranscriptionProcessor` (client-scoped, 202, require `transcribeVideos`; item route also requires a downloaded video).
- The transcript is shown on the item detail page (Web-UI) and exposed on the single-item API GET (`item:detail` group). `transcriptStatus`/`transcriptError` are in `item:read`.
- **Ops**: whisper.cpp binary + ggml model are a manual server-side install (see `WHISPER_*` env vars). Schedule `app:transcribe --pending` in cron alongside `app:download-media --pending`.

### Changing a Profile Identifier

When an account is renamed (e.g. a new Instagram username), the profile's `identifier` can be changed while preserving the profile row and all its items. RSS.app cannot re-point an existing feed to a new source URL (PATCH only edits title/description/icon), so for RSS.app-based networks the old feed is deleted and a fresh one created (or an already-existing feed for the new URL is adopted), then its current items are imported.

- **`IdentifierChanger`** (`src/Profile/`) — shared orchestration: validates the new identifier (non-empty, matches `Network.profileUrlPattern`, not already used by another profile in the same network — throws `IdentifierChangeException` otherwise), sets it, calls `FeedRegistrar::relinkRssAppFeed()`, flushes. Returns an `IdentifierChangeResult` describing the outcome.
- **`FeedRegistrar::relinkRssAppFeed(Profile)`** — the RSS.app side: deletes the old feed (best-effort), creates/links the new feed for the (already updated) identifier, imports its items. Non-RSS networks return `identifierOnly()`; a failed feed re-creation returns `relinkFailed()` (identifier stays changed, `rssAppFeedId` cleared — re-register manually).
- **Web UI**: "Identifier ändern" card on the profile show page → `POST /profiles/{id}/change-identifier` (`ProfileController::changeIdentifier`).
- **API**: `POST /api/profiles/{id}/change-identifier` (body `{"identifier": "…"}`) via `ProfileChangeIdentifierProcessor` (200, client-scoped; 422 on invalid/duplicate identifier). The generic `PATCH`/`PUT` still change `identifier` as a plain field **without** RSS.app re-linking — use the dedicated action to re-link.

### Public Group Page

A group can expose an unauthenticated, mobile-first feed page (Instagram-style
single column) at `/p/{publicSlug}`, configurable per group.

- **`Group` public-page fields** (see the entity above): `publicPageEnabled` gates reachability, `publicSlug` (unique, unguessable) is the URL token, `publicPasswordHash` an optional password, `publicTitle`/`publicDescription` the heading, `showPhotos`/`showVideos`/`showTranscript`/`showCaptions` the content toggles, `timeWindowDays` the look-back window (null = all).
- **`PublicSlugGenerator`** (`src/Group/`) — 16-char base62 slug, collision-checked against `public_slug`. A slug is generated automatically the first time the page is enabled (Web-UI controller and API processor); it is never overwritten on later edits and can be rotated via the regenerate action.
- **`PublicGroupController`** (`/p/{slug}`, no auth) — `page` renders the feed, `more` returns the next infinite-scroll fragment, `unlock` handles the password gate (verified password stored in the session). **Media/caption exposure is decided server-side** in `buildViewItems()` — disabled photos/videos/transcripts never reach the browser. Uses `ItemRepository::findPaginatedForPublicGroup()` / `countForPublicGroup()` (live members, hidden/deleted excluded, optional time window). 404 when the group is missing or its page is disabled.
- **Security**: `/p/` is opened as `PUBLIC_ACCESS` in `security.yaml` (before the admin catch-all). Password-protected pages are gated purely in the controller via a session flag — no user/login system.
- **Rendering**: standalone Twig under `templates/public/` (`base` with its own light/dark CSS, `group`, `_cards`, `_card`, `password`), a dedicated `public` importmap entrypoint (`assets/public.js`) that boots only the `public_feed` Stimulus controller (infinite scroll + "… mehr" caption clamp), and the `public_linkify` Twig filter (`src/Twig/`, escapes then linkifies URLs/hashtags/mentions).
- **Admin Web-UI**: "Öffentliche Seite" section in `GroupType` (enable, title, description, time window, content toggles, optional password with a remove toggle — a blank password field keeps the existing one), a status/URL card on the group show page, and `POST /groups/{id}/regenerate-slug`.
- **REST API**: the public-page fields are in `group:read`/`group:write`; `publicPassword` is write-only (hashed by the entity, never returned) and `publicUrl` is a computed absolute URL added by `GroupPublicUrlNormalizer`. Enabling the page via POST/PUT/PATCH auto-generates the slug; PUT preserves the non-writable slug.

### Serializer

Custom `App\Serializer\Serializer` (not Symfony's framework serializer). Uses `NullableDateTimeNormalizer` to handle null values and Unix timestamps from the API. CamelCase-to-snake_case name conversion. `SKIP_NULL_VALUES` on serialization.

### Multi-Tenancy (Client System)

- `Client` entity authenticates via Bearer token (`Authorization: Bearer <token>`)
- Profiles are shared between clients via `client_profile` join table (ManyToMany)
- API endpoints (`/api/*`) are client-scoped via custom State Providers/Processors
- POST a profile: creates or links existing profile (idempotent), reactivates soft-deleted profiles
- DELETE a profile: unlinks from client; soft-deletes (`deleted=true`) when no clients remain
- Profiles and items are never physically deleted — historical data is preserved
- CLI commands and feed fetching run system-wide, no client context
- Security: `ApiTokenAuthenticator` + `ClientTokenUserProvider`, stateless firewall for `/api/*`

### Web UI

- **Authentication**: Form login at `/login`, in-memory admin user via `WEB_ADMIN_USERNAME`/`WEB_ADMIN_PASSWORD_HASH` env vars, implemented in `WebUserProvider`
- **Controllers**: `DashboardController`, `NetworkController`, `ProfileController`, `ItemController`, `ClientController`, `GroupController`, `LoginController`. `PublicGroupController` serves the unauthenticated public group page at `/p/{slug}` (see "Public Group Page").
- **Frontend stack**: Bootstrap 5.3, Stimulus controllers, Handlebars templates, Symfony Asset Mapper (no build step)
- **Stimulus controllers** (`assets/controllers/`): `profile_list_controller`, `item_list_controller`, `profile_fetch_controller`, `profile_toggle_controller`, `toggle_controller`, `confirm_controller`, `flash_controller`, `media_download_controller`, `public_feed_controller` (public group page, loaded via the separate `public` importmap entrypoint)
- **CSS**: Custom design system in `assets/styles/app.css` (dark sidebar, stat cards, network cards, data tables)
- **AJAX patterns**: Profile and item lists use Stimulus + Handlebars for client-side rendering with AJAX pagination, search, and filtering. Controllers return JSON when `X-Requested-With: XMLHttpRequest` header is present.

### REST API (API Platform)

- All endpoints under `/api/` require Bearer token (except `/api/docs`)
- Profiles, items: client-scoped via custom State Providers/Processors
- Timeline endpoint: `GET /api/timeline` (chronological feed, filters: limit, since, until, network)
- `PATCH /api/profiles/{id}` (merge-patch): partial profile update, e.g. toggle `savePhotos`/`saveVideos`
- `POST /api/profiles/{id}/change-identifier`: change a profile's identifier (body `{"identifier": "…"}`), re-linking the RSS.app feed for RSS.app networks (200, client-scoped, `ProfileChangeIdentifierProcessor`). See "Changing a Profile Identifier" below
- `POST /api/profiles/{id}/download-media` and `POST /api/items/{id}/download-media`: queue media (re)download (202, client-scoped, drained by `app:download-media --pending`)
- `POST /api/profiles/{id}/transcribe` and `POST /api/items/{id}/transcribe`: queue video (re)transcription (202, client-scoped, require `transcribeVideos`, drained by `app:transcribe --pending`)
- Groups: full CRUD `GET/POST/PUT/PATCH/DELETE /api/groups[/{id}]` (writes via `ClientScopedGroupProcessor`, GET scoped by `ClientScopedGroupExtension`). Membership convenience routes `POST /api/groups/{id}/profiles` + `DELETE /api/groups/{id}/profiles/{profileId}` in `GroupMembershipController`. Combined feed `GET /api/groups/{groupId}/items` via `GroupItemsProvider` (filters since/until/network, excludes hidden/deleted), plus RSS at `GET /api/feeds/groups/{id}.rss`. Public-page settings (incl. write-only `publicPassword`, computed `publicUrl`) are read/written on the same resource — see "Public Group Page". Referenced profiles must belong to the client (400); foreign groups 404.
- Networks: public read access
- OpenAPI docs at `/api/docs`

## Adding a New Network Fetcher

1. Create directory `src/NetworkFeedFetcher/YourNetwork/`
2. Create `YourNetworkFeedFetcher` extending `AbstractNetworkFeedFetcher`
3. Override `getNetworkIdentifier()` if class name doesn't follow `{Network}FeedFetcher` pattern
4. Create `IdentifierParser` (profiles in DB use URLs as identifiers)
5. Create `EntryConverter` to map API data to `SocialNetworkFeedItem`
6. Add tests in `tests/NetworkFeedFetcher/YourNetwork/`
7. Register the network in the database (via Web UI at `/networks/new` or migration)

No service registration needed — autoconfiguration handles it.

## Git Workflow

- Never commit directly to `main`. Every feature, fix, or refactoring gets its own branch.
- Never squash commits. Keep individual commits as logical, reviewable work packages so each step remains traceable.
- PRs must have appropriate labels (e.g. `bug`, `enhancement`, `AI-generated`) and be assigned to `maltehuebner`.
- PRs may only be merged after all tests have passed (`bin/phpunit`).
- Delete remote branches after merging.

## Documentation

Keep `README.md` up to date when adding new networks, commands, or changing project structure.

## Standalone Mode (Import Commands)

The app can run as a standalone instance that imports data from the criticalmass.in API into a local database:

- `app:import-profiles` — imports profile metadata
- `app:import-items` — imports feed items per profile (paginated API, batch DB writes)
  - `MAX_ITEMS_PER_PROFILE = 5000` prevents stalling on profiles with excessive items
  - Uses `BATCH_SIZE = 200` for EntityManager flush/clear cycles

## Environment Variables

Key vars in `.env`: `CRITICALMASS_HOSTNAME` (API base URL), `RSS_APP_API_KEY`, `RSS_APP_API_SECRET`, `WEB_ADMIN_USERNAME`, `WEB_ADMIN_PASSWORD_HASH`, `DATABASE_URL`, `WHISPER_CLI_PATH`/`WHISPER_MODEL_PATH`/`WHISPER_LANGUAGE` (video transcription; empty model path disables it). The `$criticalmassHostname` binding in `services.yaml` injects into `ProfileFetcher`, `ImportProfilesCommand`, `ImportItemsCommand`, and `ProfilePersister`.

## Local Development

```bash
docker compose up -d                                 # start PostgreSQL
symfony serve -d                                     # start web server
php bin/console doctrine:migrations:migrate          # run migrations
```
