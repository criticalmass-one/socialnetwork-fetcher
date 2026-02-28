# SocialNetwork Fetcher

Symfony 8 CLI application that fetches social media feeds from various networks and pushes them to the [criticalmass.in](https://criticalmass.in) API.

## Requirements

- PHP 8.5+
- Composer

## Installation

```bash
composer install
cp .env .env.local
```

Edit `.env.local` with your credentials:

```env
CRITICALMASS_HOSTNAME=https://criticalmass.in
RSS_APP_API_KEY=your_key
RSS_APP_API_SECRET=your_secret
```

## Usage

### Fetch feeds

```bash
# Fetch all networks
php bin/console feeds:fetch

# Fetch specific networks
php bin/console feeds:fetch mastodon bluesky

# Fetch with options
php bin/console feeds:fetch instagram_profile --count=50 --citySlug=hamburg
```

Options:

| Option | Short | Description |
|---|---|---|
| `--count` | `-c` | Number of items per profile |
| `--fromDateTime` | `-f` | Start date filter |
| `--untilDateTime` | `-u` | End date filter |
| `--includeOldItems` | `-i` | Include already fetched items |
| `--citySlug` | | Filter by city |

### List registered networks

```bash
php bin/console network:list
```

```
 ------------------- -------------------------------------------------------
  Network             Fetcher
 ------------------- -------------------------------------------------------
  bluesky             App\NetworkFeedFetcher\Bluesky\BlueskyFeedFetcher
  facebook_page       App\NetworkFeedFetcher\Facebook\FacebookFeedFetcher
  homepage            App\NetworkFeedFetcher\Homepage\HomepageFeedFetcher
  instagram_profile   App\NetworkFeedFetcher\Instagram\InstagramFeedFetcher
  mastodon            App\NetworkFeedFetcher\Mastodon\MastodonFeedFetcher
  threads_profile     App\NetworkFeedFetcher\Threads\ThreadFeedFetcher
 ------------------- -------------------------------------------------------
```

### List feeds/profiles

```bash
# List all
php bin/console feed:list

# Filter by network
php bin/console feed:list mastodon bluesky
```

## Supported Networks

| Network | Identifier | API | Auth required |
|---|---|---|---|
| Mastodon | `mastodon` | Mastodon API v1 | No |
| Bluesky | `bluesky` | AT Protocol (public) | No |
| Instagram | `instagram_profile` | via RSS.app | Yes (RSS.app) |
| Facebook | `facebook_page` | via RSS.app | Yes (RSS.app) |
| Threads | `threads_profile` | via RSS.app | Yes (RSS.app) |
| Homepage/RSS | `homepage` | Direct RSS/Atom | No |

## Architecture

```
feeds:fetch command
    |
    v
FeedFetcher (orchestrator)
    |
    +-- ProfileFetcher -----------> criticalmass.in API (load profiles)
    |
    +-- NetworkFeedFetcher -------> Network API (fetch feed items)
    |   (Mastodon, Bluesky, ...)
    |
    +-- FeedItemPersister --------> criticalmass.in API (push items)
    |
    +-- ProfilePersister ---------> criticalmass.in API (update metadata)
```

Network fetchers are auto-discovered via Symfony's autoconfiguration. Any class implementing `NetworkFeedFetcherInterface` is automatically registered — no manual service configuration needed.

### Adding a new network

1. Create `src/NetworkFeedFetcher/YourNetwork/YourNetworkFeedFetcher.php` extending `AbstractNetworkFeedFetcher`
2. Create `IdentifierParser.php` to extract handles from URL-based identifiers
3. Create `EntryConverter.php` to map API responses to `SocialNetworkFeedItem`
4. Add tests in `tests/NetworkFeedFetcher/YourNetwork/`

## Testing

```bash
# Run all tests
bin/phpunit

# Run a specific test file
bin/phpunit tests/NetworkFeedFetcher/Bluesky/EntryConverterTest.php

# Filter by test name
bin/phpunit --filter testConvertValidEntry
```

## Git Workflow

- Every feature, fix, or refactoring gets its own branch — never commit directly to `main`.
- Never squash commits. Keep individual commits as logical, reviewable work packages.
- PRs must have appropriate labels and be assigned to the maintainer.
- PRs may only be merged after all tests have passed.
- Delete remote branches after merging.
