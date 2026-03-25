# SocialNetwork Fetcher

Symfony 8 application that fetches social media feeds from various networks and aggregates them. Originally designed to push data to the [criticalmass.in](https://criticalmass.in) API, it also supports standalone mode with a local PostgreSQL database, a web-based admin UI, and a multi-tenant REST API for external clients.

## Requirements

- PHP 8.5+
- Composer
- Docker & Docker Compose (for PostgreSQL)
- yt-dlp (optional, for video downloads and Instagram/Threads/Facebook photo extraction)

## Installation

```bash
git clone git@github.com:criticalmass-one/socialnetwork-fetcher.git
cd socialnetwork-fetcher
composer install
```

### Database setup

Start PostgreSQL via Docker Compose:

```bash
docker compose up -d
```

This starts a PostgreSQL 16 container with a persistent volume. The default credentials are configured in `compose.yaml` (`app` / `!ChangeMe!`).

Create and migrate the database:

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

### Configuration

Copy the environment file and adjust it:

```bash
cp .env .env.local
```

Key environment variables:

| Variable | Purpose | Default |
|---|---|---|
| `DATABASE_URL` | PostgreSQL connection string | see `compose.yaml` |
| `CRITICALMASS_HOSTNAME` | criticalmass.in API hostname | `criticalmass.in` |
| `RSS_APP_API_KEY` | RSS.app API key (for Instagram, Facebook, Threads) | — |
| `RSS_APP_API_SECRET` | RSS.app API secret | — |
| `WEB_ADMIN_USERNAME` | Web UI admin username | `admin` |
| `WEB_ADMIN_PASSWORD_HASH` | Bcrypt hash for web login | `$2y$13$changeme` |

Generate a password hash for the web admin:

```bash
php bin/console security:hash-password 'your-password'
```

Then set `WEB_ADMIN_PASSWORD_HASH` in `.env.local` to the generated hash.

### Start the dev server

```bash
symfony serve -d
```

The application is accessible at `https://127.0.0.1:8000`.

## Supported Networks

| Network | Identifier | API | Auth required |
|---|---|---|---|
| Mastodon | `mastodon` | Mastodon API v1 | No |
| Bluesky | `bluesky_profile` | AT Protocol (public) | No |
| Homepage/RSS | `homepage` | Direct RSS/Atom feed | No |
| Instagram | `instagram_profile` | via RSS.app | Yes (RSS.app) |
| Facebook | `facebook_page` | via RSS.app | Yes (RSS.app) |
| Threads | `threads_profile` | via RSS.app | Yes (RSS.app) |

## Console Commands

### Feed fetching

```bash
# Fetch all networks
php bin/console fetch-feed

# Fetch specific networks
php bin/console fetch-feed mastodon bluesky_profile

# Fetch with options
php bin/console fetch-feed instagram_profile --count=50

# Run scheduled fetches (based on cron expressions per network)
php bin/console app:fetch-scheduled
```

Options for `fetch-feed`:

| Option | Short | Description |
|---|---|---|
| `--count` | `-c` | Number of items per profile |
| `--fromDateTime` | `-f` | Start date filter |
| `--untilDateTime` | `-u` | End date filter |
| `--includeOldItems` | `-i` | Include already fetched items |

### Profile & network management

```bash
php bin/console network:list                          # list registered network fetchers
php bin/console feed:list                             # list all profiles
php bin/console feed:list mastodon bluesky            # list profiles by network
```

### Standalone mode (data import)

Import data from the criticalmass.in API into the local database:

```bash
php bin/console app:import-profiles                   # import profile metadata
php bin/console app:import-items -v                   # import feed items per profile
php bin/console app:import-items --network=twitter    # import only one network
php bin/console app:import-items --dry-run            # preview without writing
```

Import limits: max 5000 items per profile, batch size 200 for database writes.

### API client management

```bash
php bin/console app:client:create <name>              # create client, outputs Bearer token
php bin/console app:client:list                       # list all clients
php bin/console app:client:regenerate-token <name>    # regenerate token
php bin/console app:client:enable <name>              # enable client
php bin/console app:client:disable <name>             # disable client
```

### RSS.app integration

```bash
php bin/console app:rssapp:sync-feed-ids              # sync RSS.app feed IDs to DB
php bin/console app:rssapp:sync-feed-ids --dry-run    # preview
php bin/console app:rssapp:sync-feed-ids --network=instagram_profile
php bin/console app:rssapp:sync-feed-ids --force      # re-check existing feed IDs
```

### Media download

Download photos and videos for feed items. For Instagram, Threads, and Facebook, photos (including carousel/album images) are extracted in original quality via `yt-dlp`, with a fallback to the RSS.app thumbnail. Bluesky and Mastodon photos are downloaded directly from their API response (supports multiple photos per post natively). Videos are downloaded via `yt-dlp` from the item's permalink URL.

```bash
php bin/console app:download-media                    # all profiles with savePhotos/saveVideos enabled
php bin/console app:download-media --profile=42       # specific profile
php bin/console app:download-media --retry-failed     # retry previously failed downloads
php bin/console app:download-media --photos-only      # only photos
php bin/console app:download-media --videos-only      # only videos (requires yt-dlp)
```

Media files are stored in `public/media/{profileId}/{itemId}/`. Profiles must have `savePhotos` and/or `saveVideos` enabled (toggle in Web UI or API). When enabled, media is also downloaded automatically after each feed fetch.

## Web UI

The admin interface is accessible after login at `/login`. It provides:

- **Dashboard** — Overview with network statistics (item counts per 24h/7d/31d/365d by publication date), profile/item counts, and a table of recent items
- **Networks** — CRUD for social networks (name, icon, color, cron schedule)
- **Profiles** — Searchable/filterable list with auto-fetch toggle, save photos/videos toggles, fetch status, manual fetch trigger, RSS.app registration
- **Items** — Searchable/filterable list with hide/delete toggles, media status indicators, network and profile filters, manual media download
- **Clients** — API client management with token display, enable/disable

The frontend uses Bootstrap 5, Stimulus controllers for interactive features (toggles, AJAX pagination, search), and Handlebars for client-side template rendering. Assets are managed via Symfony Asset Mapper (no build step needed).

## REST API

All API endpoints under `/api/` require Bearer token authentication:

```
Authorization: Bearer <token>
```

Tokens are generated via `app:client:create`.

### Endpoints

**Profiles** (client-scoped):
- `GET /api/profiles` — List profiles linked to the authenticated client
- `GET /api/profiles/{id}` — Get single profile
- `POST /api/profiles` — Create or link an existing profile (idempotent)
- `PUT /api/profiles/{id}` — Update profile
- `DELETE /api/profiles/{id}` — Unlink from client; soft-deletes if no other clients remain

**Items** (client-scoped):
- `GET /api/items` — List feed items (paginated, 50 per page)
- `GET /api/items/{id}` — Get single item
- `POST /api/items` — Create item
- `PUT /api/items/{id}` — Update item

**Timeline**:
- `GET /api/timeline` — Chronological feed (default: last 24h, max 100 items)
  - Query params: `limit`, `since`, `until`, `network`

**Networks** (public):
- `GET /api/networks` — List all networks
- `GET /api/networks/{id}` — Get single network

**Documentation**:
- `GET /api/docs` — Interactive OpenAPI documentation

### Multi-tenancy

Profiles are shared across clients via a join table. Each client sees only its linked profiles and their items. Creating a profile that already exists links it to the client (idempotent). Deleting a profile unlinks it; it is soft-deleted only when no clients reference it. Profiles and items are never physically deleted.

## Architecture

### Feed fetching flow

```
fetch-feed command
    |
    v
FeedFetcher (orchestrator)
    |
    +-- ProfileFetcher -----------> Load profiles (DB or API)
    |
    +-- NetworkFeedFetcher -------> Network API (fetch feed items)
    |   (Mastodon, Bluesky, ...)
    |
    +-- FeedItemPersister --------> Persist items
    |
    +-- ProfilePersister ---------> Update profile metadata + fetch timestamps
    |
    +-- MediaDownloadService -----> Download photos/videos (if profile flags set)
```

After each fetch, the profile's `lastFetchSuccessDateTime` or `lastFetchFailureDateTime`/`lastFetchFailureError` fields are updated.

### Service wiring

Network fetchers are auto-discovered: any class implementing `NetworkFeedFetcherInterface` gets tagged via `Kernel::build()` and injected into `FeedFetcher` through the `SocialNetworkFetcherPass` compiler pass. No manual service registration needed.

### Network fetcher types

- **Direct API fetchers**: `MastodonFeedFetcher`, `BlueskyFeedFetcher`, `HomepageFeedFetcher` — call the network's API directly
- **RSS.app-based fetchers**: `FacebookFeedFetcher`, `InstagramFeedFetcher`, `ThreadFeedFetcher` — proxy through RSS.app's API

### Each network fetcher directory contains

```
src/NetworkFeedFetcher/YourNetwork/
    *FeedFetcher.php       — implements NetworkFeedFetcherInterface
    IdentifierParser.php   — extracts handle/username from URL identifiers
    EntryConverter.php     — converts API response to SocialNetworkFeedItem
```

### Entities

| Entity | Purpose |
|---|---|
| `Profile` | Social network profile (URL identifier, optional title, network reference, fetch metadata, savePhotos/saveVideos flags) |
| `Item` | Feed item (text, title, permalink, timestamps, hidden/deleted flags, photoPaths, videoPath, mediaStatus) |
| `Network` | Social network definition (name, icon, colors, cron expression) |
| `Client` | API client (name, Bearer token, enabled flag, linked profiles) |

### Security

- **Web UI** (`/`): Form-based login, `ROLE_ADMIN`, in-memory user via env vars
- **API** (`/api/*`): Stateless Bearer token authentication, `ROLE_API_CLIENT`
- **API docs** (`/api/docs`): Public access

## Adding a New Network Fetcher

1. Create directory `src/NetworkFeedFetcher/YourNetwork/`
2. Create `YourNetworkFeedFetcher` extending `AbstractNetworkFeedFetcher`
3. Override `getNetworkIdentifier()` if the class name doesn't follow the `{Network}FeedFetcher` pattern
4. Create `IdentifierParser` to extract handles from URL-based identifiers
5. Create `EntryConverter` to map API data to `SocialNetworkFeedItem`
6. Add tests in `tests/NetworkFeedFetcher/YourNetwork/`
7. Register the network in the database (via Web UI or migration)

No service registration needed — autoconfiguration handles it.

## Testing

```bash
bin/phpunit                                          # run all tests
bin/phpunit tests/NetworkFeedFetcher/Bluesky/        # run test directory
bin/phpunit --filter testParseHandleFormat            # run single test
```

## Tech Stack

- **Framework**: Symfony 8.0
- **PHP**: 8.5+
- **Database**: PostgreSQL 16
- **API**: API Platform 4.2
- **Frontend**: Bootstrap 5.3, Stimulus 3.2, Handlebars 4.7
- **Assets**: Symfony Asset Mapper (no build step)
- **Testing**: PHPUnit 13
- **API Docs**: NelmioApiDocBundle (OpenAPI/Swagger)

## Git Workflow

- Never commit directly to `main`. Every feature, fix, or refactoring gets its own branch.
- Never squash commits. Keep individual commits as logical, reviewable work packages so each step remains traceable.
- PRs must have appropriate labels (e.g. `bug`, `enhancement`, `AI-generated`) and be assigned to `maltehuebner`.
- PRs may only be merged after all tests have passed.
- Delete remote branches after merging.

## License

Private repository. All rights reserved.
