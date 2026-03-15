# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Symfony 8 application that fetches social media feeds from various networks (Mastodon, Bluesky, Instagram, Facebook, Threads, Homepage/RSS). Supports multi-tenancy: API clients authenticate via Bearer token and see only their linked profiles. Console commands for feed fetching, Web-UI for administration, REST API (API Platform) for client access.

## Commands

```bash
bin/phpunit                                          # run all tests
bin/phpunit --filter testParseHandleFormat            # run single test
bin/phpunit tests/NetworkFeedFetcher/Bluesky/         # run test directory

php bin/console feeds:fetch mastodon -c 50            # fetch feeds
php bin/console feed:list instagram_profile           # list profiles
php bin/console network:list                          # list registered fetchers
php bin/console app:import-items -v                   # import items from criticalmass.in API
php bin/console app:import-items --network=twitter    # import only one network
php bin/console app:import-items --dry-run             # preview without writing

php bin/console app:client:create <name>               # create API client, outputs token
php bin/console app:client:list                         # list all clients
php bin/console app:client:regenerate-token <name>      # regenerate token
php bin/console app:client:disable <name>               # disable client
php bin/console app:client:enable <name>                # enable client

php bin/console app:rssapp:sync-feed-ids                  # sync RSS.app feed IDs to DB
php bin/console app:rssapp:sync-feed-ids --dry-run        # preview without writing
php bin/console app:rssapp:sync-feed-ids --network=instagram_profile  # only one network
php bin/console app:rssapp:sync-feed-ids --force          # re-check profiles with existing feed IDs
php bin/console app:rssapp:sync-feed-ids -v               # show all profiles including skipped
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

## Adding a New Network Fetcher

1. Create directory `src/NetworkFeedFetcher/YourNetwork/`
2. Create `YourNetworkFeedFetcher` extending `AbstractNetworkFeedFetcher`
3. Override `getNetworkIdentifier()` if class name doesn't follow `{Network}FeedFetcher` pattern
4. Create `IdentifierParser` (profiles in DB use URLs as identifiers)
5. Create `EntryConverter` to map API data to `SocialNetworkFeedItem`
6. Add tests in `tests/NetworkFeedFetcher/YourNetwork/`

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

Key vars in `.env`: `CRITICALMASS_HOSTNAME` (API base URL), `RSS_APP_API_KEY`, `RSS_APP_API_SECRET`. The `$criticalmassHostname` binding in `services.yaml` injects into `ProfileFetcher`, `ImportProfilesCommand`, `ImportItemsCommand`, and `ProfilePersister`.
