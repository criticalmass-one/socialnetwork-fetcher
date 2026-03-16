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

php bin/console feeds:fetch mastodon -c 50            # fetch feeds
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
```

## Architecture

### Feed Fetching Flow

```
Command → FeedFetcher → ProfileFetcher (loads profiles from criticalmass.in API)
                      → NetworkFeedFetcher.fetch() (per profile, network-specific)
                      → FeedItemPersister (pushes items to criticalmass.in API)
                      → ProfilePersister (updates profile metadata)
```

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

- **Profile** — Social network profile: `id`, `identifier` (URL), `title` (optional display name), `network` (FK), `autoFetch`, `fetchSource`, `additionalData` (JSON), `deleted`/`deletedAt` (soft delete). `getDisplayName()` returns `title` if set, otherwise `identifier`.
- **Item** — Feed item: `id`, `profile` (FK), `uniqueIdentifier`, `text`, `title`, `dateTime`, `permalink`, `raw`, `hidden`, `deleted`. Supports soft-delete and hide toggles.
- **Network** — Social network definition: `id`, `identifier`, `name`, `icon`, `backgroundColor`, `textColor`, `cronExpression`.
- **Client** — API client: `id`, `name`, `token`, `enabled`, `profiles` (ManyToMany via `client_profile` join table), `createdAt`.

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
- **Controllers**: `DashboardController`, `NetworkController`, `ProfileController`, `ItemController`, `ClientController`, `LoginController`
- **Frontend stack**: Bootstrap 5.3, Stimulus controllers, Handlebars templates, Symfony Asset Mapper (no build step)
- **Stimulus controllers** (`assets/controllers/`): `profile_list_controller`, `item_list_controller`, `profile_fetch_controller`, `profile_toggle_controller`, `toggle_controller`, `confirm_controller`, `flash_controller`
- **CSS**: Custom design system in `assets/styles/app.css` (dark sidebar, stat cards, network cards, data tables)
- **AJAX patterns**: Profile and item lists use Stimulus + Handlebars for client-side rendering with AJAX pagination, search, and filtering. Controllers return JSON when `X-Requested-With: XMLHttpRequest` header is present.

### REST API (API Platform)

- All endpoints under `/api/` require Bearer token (except `/api/docs`)
- Profiles, items: client-scoped via custom State Providers/Processors
- Timeline endpoint: `GET /api/timeline` (chronological feed, filters: limit, since, until, network)
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

Key vars in `.env`: `CRITICALMASS_HOSTNAME` (API base URL), `RSS_APP_API_KEY`, `RSS_APP_API_SECRET`, `WEB_ADMIN_USERNAME`, `WEB_ADMIN_PASSWORD_HASH`, `DATABASE_URL`. The `$criticalmassHostname` binding in `services.yaml` injects into `ProfileFetcher`, `ImportProfilesCommand`, `ImportItemsCommand`, and `ProfilePersister`.

## Local Development

```bash
docker compose up -d                                 # start PostgreSQL
symfony serve -d                                     # start web server
php bin/console doctrine:migrations:migrate          # run migrations
```
