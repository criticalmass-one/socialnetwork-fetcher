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
php bin/console app:download-media --retry-failed     # retry previously failed downloads
php bin/console app:download-media --photos-only      # only photos
php bin/console app:download-media --videos-only      # only videos
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

- **Direct API fetchers**: `MastodonFeedFetcher`, `BlueskyFeedFetcher`, `HomepageFeedFetcher` — each extends `AbstractNetworkFeedFetcher` and calls the network's API directly. The Mastodon fetcher stores the original API entry JSON as the item's `raw` (like Bluesky does).
- **RSS.app-based fetchers**: `FacebookFeedFetcher`, `InstagramFeedFetcher`, `ThreadFeedFetcher`, `TikTokFeedFetcher`, `TwitterFeedFetcher` — extend `RssApp\Fetcher` (abstract), which proxies through RSS.app's API

### Each Network Fetcher Directory Contains

- `*FeedFetcher.php` — implements `NetworkFeedFetcherInterface`, orchestrates fetching
- `IdentifierParser.php` — extracts handle/username from URL-based identifiers stored in the DB
- `EntryConverter.php` — converts API response to `SocialNetworkFeedItem`

### Entities

- **Profile** — Social network profile: `id`, `identifier` (URL), `title` (optional display name), `network` (FK), `autoFetch`, `fetchSource`, `savePhotos`, `saveVideos`, `additionalData` (JSON), `deleted`/`deletedAt` (soft delete). `getDisplayName()` returns `title` if set, otherwise `identifier`.
- **Item** — Feed item: `id`, `profile` (FK), `uniqueIdentifier`, `text`, `title`, `dateTime`, `permalink`, `raw`, `hidden`, `deleted`, `photoPaths` (JSON array), `videoPath`, `mediaStatus`, `mediaError`. Supports soft-delete, hide toggles, and media downloads.
- **Network** — Social network definition: `id`, `identifier`, `name`, `icon`, `backgroundColor`, `textColor`, `profileUrlPattern`, `cronExpression`.
- **Client** — API client: `id`, `name`, `token`, `enabled`, `profiles` (ManyToMany via `client_profile` join table), `createdAt`.
- **Group** — Client-scoped bundle of profiles (`profile_group` table): `id`, `name` (unique per client, validated via `UniqueEntity` + `NotBlank`), `client` (FK, required), `description`, `color`, `profiles` (ManyToMany via `profile_group_profile`). Deleting a group never touches profiles.

**Gotcha:** The `profile` table has **no ID sequence** (IDs historically come from the criticalmass.in import). New profiles need an explicit ID — the API POST handler uses `ProfileRepository::findNextFreeId()` (MAX+1, race-prone workaround; proper identity column tracked in issue #86).

### Media Download System

Profiles can opt into automatic photo/video downloads via `savePhotos` and `saveVideos` flags. Media is stored via Flysystem (local adapter at `public/media/`).

- **`MediaUrlExtractor`** — extracts photo URLs from `raw` JSON (RSS.app `description_html` img tags, RSS.app `thumbnail` fallback, Bluesky `post.embed` images, Mastodon `media_attachments[]`). Supports multiple photos per item. Video URL is the item's `permalink` — for Mastodon/Bluesky only when the raw payload actually contains a video attachment/embed (posts without video are skipped instead of failing in yt-dlp); all other networks are probed blindly.
- **`PhotoDownloader`** — downloads images via HttpClient, stores as `{profileId}/{itemId}/photo_{index}.{ext}` in Flysystem
- **`YtDlpPhotoDownloader`** — extracts photos (including carousel/album images) via `yt-dlp` in original quality. Used for Instagram, Threads, and Facebook where RSS.app only provides a single thumbnail. Falls back to `PhotoDownloader` if `yt-dlp` is unavailable or returns no results.
- **`VideoDownloader`** — downloads videos via `yt-dlp` process, stores as `{profileId}/{itemId}/video.{ext}` on disk. Requires `yt-dlp` to be installed; gracefully skips if unavailable.
- **`MediaDownloadService`** — orchestrates downloads, manages `mediaStatus` lifecycle (`downloading` → `completed`/`failed`). Selects download strategy per network: `yt-dlp` for RSS.app-based networks (Instagram, Threads, Facebook), direct URL download for Bluesky/Mastodon.
- **`DownloadMediaCommand`** — CLI for bulk downloads with `--profile`, `--retry-failed`, `--photos-only`, `--videos-only` options
- Auto-download triggers after feed fetch when profile has `savePhotos`/`saveVideos` enabled
- Manual download via button on item detail page (Stimulus `media_download_controller`)
- Item entity stores `photoPaths` (JSON array of relative paths), `videoPath` (string), `mediaStatus`, `mediaError`

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

### Groups

- A `Group` belongs to exactly one client; profiles can be members of any number of groups
- **Membership rule (keep consistent everywhere):** admins may add *any* profile to *any* group; client-token users only profiles linked to their client. Enforced identically in `ProfileController::addToGroups()` and `GroupController::addProfile()`.
- Members are managed via the searchable picker on the group show page (`app_group_profile_add` + JSON search endpoint `app_group_profile_search`, max 20 results, members and deleted profiles excluded) and via the profile show page. The `GroupType` form deliberately has **no** profiles field — editing a group never touches its member collection.
- API: `/api/groups` CRUD is client-scoped via `ClientScopedGroupProcessor`, which assigns the client *after* validation — that's why the client requirement lives as a form constraint in `GroupType`, not as an entity-level `Assert\NotNull`. Group timeline at `/api/groups/{id}/items`, RSS feed at `/api/feeds/groups/{id}.rss`.

### Web UI

- **Authentication**: Form login at `/login`, in-memory admin user via `WEB_ADMIN_USERNAME`/`WEB_ADMIN_PASSWORD_HASH` env vars, implemented in `WebUserProvider`. Client-token users can log in via `/login/client-token` (`WebTokenAuthenticator`) and see only the group pages.
- **Controllers**: `DashboardController`, `NetworkController`, `ProfileController`, `ItemController`, `ClientController`, `GroupController`, `LoginController`
- **Frontend stack**: Bootstrap 5.3, Stimulus controllers, Handlebars templates, Symfony Asset Mapper (no build step)
- **Stimulus controllers** (`assets/controllers/`): `profile_list_controller`, `item_list_controller`, `profile_fetch_controller`, `profile_toggle_controller`, `toggle_controller`, `confirm_controller`, `flash_controller`, `media_download_controller`, `group_profile_picker_controller`, `rssapp_table_controller`, `csrf_protection_controller`
- **CSS**: Custom design system in `assets/styles/app.css` (dark sidebar, stat cards, network cards, data tables)
- **AJAX patterns**: Profile and item lists use Stimulus + Handlebars for client-side rendering with AJAX pagination, search, and filtering. Controllers return JSON when `X-Requested-With: XMLHttpRequest` header is present.
- **CSRF (two mechanisms!):** Symfony forms (login, GroupType, …) use *stateless* CSRF — `csrf_protection_controller.js` generates a random token on submit and double-submits it as field value + `csrf-token_<token>` cookie. **This controller is load-bearing; without JS every form submit fails.** Plain action forms (`csrf_token('…')` in Twig: toggles, delete, group add/remove) use classic session-based tokens. When driving the UI via HTTP (curl), generate a fresh double-submit pair per form POST (the cookie is consumed on validation) and parse session tokens from the rendered HTML.

### REST API (API Platform)

- All endpoints under `/api/` require Bearer token (except `/api/docs`)
- Profiles, items, groups: client-scoped via custom State Providers/Processors
- Timeline endpoint: `GET /api/timeline` (chronological feed, filters: limit, since, until, network)
- Group timeline: `GET /api/groups/{id}/items`; RSS feeds at `/api/feeds/groups/{id}.rss`
- Networks: public read access
- OpenAPI docs at `/api/docs`

## Testing

- Functional tests run against **SQLite** (`var/data_test.db`, see `.env.test`); production uses PostgreSQL. The schema is **not** rebuilt automatically — after entity/migration changes run `php bin/console doctrine:schema:drop --env=test --force && php bin/console doctrine:schema:create --env=test`, otherwise functional tests fail with `InvalidFieldNameException`.
- Functional tests purge + load fixtures themselves (`NetworkFixtures`, `TestFixtures`) per test, see `tests/Functional/AbstractApiTestCase.php`.
- **Web tests:** log in with `$client->loginUser(static::getContainer()->get(WebUserProvider::class)->loadUserByIdentifier('admin'), 'main')` — a hand-built `InMemoryUser` with a different password hash gets silently deauthenticated by the user-changed check on the next request (redirects to /login). Example: `tests/Functional/Web/GroupManagementTest.php`.
- The ~90 risky-test warnings ("did not remove its own exception handlers") are a known pre-existing issue (#81); do **not** add `restore_exception_handler()` in `tearDown()` — PHPUnit 13 then complains about removed handlers instead.

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

Key vars in `.env`: `CRITICALMASS_HOSTNAME` (API base URL), `RSS_APP_API_KEY`, `RSS_APP_API_SECRET`, `WEB_ADMIN_USERNAME`, `WEB_ADMIN_PASSWORD_HASH`, `DATABASE_URL`. The `$criticalmassHostname` binding in `services.yaml` injects into `ProfileFetcher`, `ImportProfilesCommand`, `ImportItemsCommand`, and `ProfilePersister`.

## Local Development

```bash
docker compose up -d                                 # start PostgreSQL
symfony serve -d                                     # start web server
php bin/console doctrine:migrations:migrate          # run migrations
```

**Port drift gotcha:** `compose.override.yaml` maps PostgreSQL port `"5432"` without a fixed host port — Docker assigns a random one whenever the container is recreated, silently invalidating `DATABASE_URL` in `.env.local` (symptom: "Connection refused" on a stale port). Check with `docker compose ps` and update `.env.local`, or use `symfony console` instead of `php bin/console` (the Symfony CLI injects the current Docker port automatically).
