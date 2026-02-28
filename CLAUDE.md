# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Symfony 8 CLI application that fetches social media feeds from various networks (Mastodon, Bluesky, Instagram, Facebook, Threads, Homepage/RSS) and pushes them to the criticalmass.in API. No web routes — console commands only.

## Commands

```bash
bin/phpunit                                          # run all tests
bin/phpunit --filter testParseHandleFormat            # run single test
bin/phpunit tests/NetworkFeedFetcher/Bluesky/         # run test directory

php bin/console feeds:fetch mastodon -c 50            # fetch feeds
php bin/console feed:list instagram_profile           # list profiles
php bin/console network:list                          # list registered fetchers
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

## Environment Variables

Key vars in `.env`: `CRITICALMASS_HOSTNAME` (API base URL), `RSS_APP_API_KEY`, `RSS_APP_API_SECRET`. The `$criticalmassHostname` binding in `services.yaml` injects into `ProfileFetcher`, `ApiPusher`, and `ProfilePersister`.
