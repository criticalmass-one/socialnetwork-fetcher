<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Profile;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ProfileFixtures extends Fixture implements DependentFixtureInterface
{
    public const PROFILE_HOMEPAGE_CRITICALMASS = 'profile-homepage-criticalmass';

    public const PROFILE_FACEBOOK_PROFILE_CRITICALMASS = 'profile-facebook-profile-criticalmass';
    public const PROFILE_FACEBOOK_GROUP_CRITICALMASS   = 'profile-facebook-group-criticalmass';
    public const PROFILE_FACEBOOK_EVENT_CRITICALMASS   = 'profile-facebook-event-criticalmass';
    public const PROFILE_FACEBOOK_PAGE_CRITICALMASS    = 'profile-facebook-page-criticalmass';

    public const PROFILE_INSTAGRAM_PROFILE_CRITICALMASS = 'profile-instagram-profile-criticalmass';
    public const PROFILE_INSTAGRAM_PHOTO_CRITICALMASS   = 'profile-instagram-photo-criticalmass';

    public const PROFILE_TWITTER_CRITICALMASS = 'profile-twitter-criticalmass';

    public const PROFILE_MASTODON_CRITICALMASS = 'profile-mastodon-criticalmass';

    public const PROFILE_BLUESKY_PROFILE_CRITICALMASS = 'profile-bluesky-profile-criticalmass';

    public const PROFILE_THREADS_PROFILE_CRITICALMASS = 'profile-threads-profile-criticalmass';
    public const PROFILE_THREADS_POST_CRITICALMASS    = 'profile-threads-post-criticalmass';

    public const PROFILE_DISCORD_CHAT_CRITICALMASS  = 'profile-discord-chat-criticalmass';
    public const PROFILE_TELEGRAM_CHAT_CRITICALMASS = 'profile-telegram-chat-criticalmass';
    public const PROFILE_WHATSAPP_CHAT_CRITICALMASS = 'profile-whatsapp-chat-criticalmass';

    public const PROFILE_FLICKR_CRITICALMASS = 'profile-flickr-criticalmass';
    public const PROFILE_TUMBLR_CRITICALMASS = 'profile-tumblr-criticalmass';
    public const PROFILE_GOOGLE_CRITICALMASS = 'profile-google-criticalmass';

    public const PROFILE_STRAVA_ACTIVITY_CRITICALMASS = 'profile-strava-activity-criticalmass';
    public const PROFILE_STRAVA_CLUB_CRITICALMASS     = 'profile-strava-club-criticalmass';
    public const PROFILE_STRAVA_ROUTE_CRITICALMASS    = 'profile-strava-route-criticalmass';

    public const PROFILE_YOUTUBE_CHANNEL_CRITICALMASS  = 'profile-youtube-channel-criticalmass';
    public const PROFILE_YOUTUBE_USER_CRITICALMASS     = 'profile-youtube-user-criticalmass';
    public const PROFILE_YOUTUBE_PLAYLIST_CRITICALMASS = 'profile-youtube-playlist-criticalmass';
    public const PROFILE_YOUTUBE_VIDEO_CRITICALMASS    = 'profile-youtube-video-criticalmass';

    public function load(ObjectManager $manager): void
    {
        $profiles = [
            // Homepage
            self::PROFILE_HOMEPAGE_CRITICALMASS => [
                'identifier' => 'https://criticalmass.berlin',
                'network' => NetworkFixtures::NETWORK_HOMEPAGE,
            ],

            // Facebook
            self::PROFILE_FACEBOOK_PROFILE_CRITICALMASS => [
                'identifier' => 'https://www.facebook.com/profile.php?id=100000000000000',
                'network' => NetworkFixtures::NETWORK_FACEBOOK_PROFILE,
            ],
            self::PROFILE_FACEBOOK_GROUP_CRITICALMASS => [
                'identifier' => 'https://www.facebook.com/groups/criticalmassberlin',
                'network' => NetworkFixtures::NETWORK_FACEBOOK_GROUP,
            ],
            self::PROFILE_FACEBOOK_EVENT_CRITICALMASS => [
                'identifier' => 'https://www.facebook.com/events/123456789012345',
                'network' => NetworkFixtures::NETWORK_FACEBOOK_EVENT,
            ],
            self::PROFILE_FACEBOOK_PAGE_CRITICALMASS => [
                'identifier' => 'https://www.facebook.com/criticalmass.berlin',
                'network' => NetworkFixtures::NETWORK_FACEBOOK_PAGE,
            ],

            // Instagram
            self::PROFILE_INSTAGRAM_PROFILE_CRITICALMASS => [
                'identifier' => 'https://www.instagram.com/criticalmass_berlin/',
                'network' => NetworkFixtures::NETWORK_INSTAGRAM_PROFILE,
            ],
            self::PROFILE_INSTAGRAM_PHOTO_CRITICALMASS => [
                'identifier' => 'https://www.instagram.com/p/CrItIcAlMaSs01/',
                'network' => NetworkFixtures::NETWORK_INSTAGRAM_PHOTO,
            ],

            // Twitter / X
            self::PROFILE_TWITTER_CRITICALMASS => [
                'identifier' => 'https://x.com/CM_Berlin',
                'network' => NetworkFixtures::NETWORK_TWITTER,
            ],

            // Mastodon
            self::PROFILE_MASTODON_CRITICALMASS => [
                'identifier' => '@criticalmass@mastodon.social',
                'network' => NetworkFixtures::NETWORK_MASTODON,
            ],

            // Bluesky
            self::PROFILE_BLUESKY_PROFILE_CRITICALMASS => [
                'identifier' => 'criticalmass.bsky.social',
                'network' => NetworkFixtures::NETWORK_BLUESKY_PROFILE,
            ],

            // Threads
            self::PROFILE_THREADS_PROFILE_CRITICALMASS => [
                'identifier' => 'https://threads.net/@criticalmass.berlin',
                'network' => NetworkFixtures::NETWORK_THREADS_PROFILE,
            ],
            self::PROFILE_THREADS_POST_CRITICALMASS => [
                'identifier' => 'https://threads.net/@criticalmass.berlin/post/1234567890/',
                'network' => NetworkFixtures::NETWORK_THREADS_POST,
            ],

            // Chats
            self::PROFILE_DISCORD_CHAT_CRITICALMASS => [
                'identifier' => 'https://discord.gg/criticalmassberlin',
                'network' => NetworkFixtures::NETWORK_DISCORD_CHAT,
            ],
            self::PROFILE_TELEGRAM_CHAT_CRITICALMASS => [
                'identifier' => 'https://t.me/criticalmassberlin',
                'network' => NetworkFixtures::NETWORK_TELEGRAM_CHAT,
            ],
            self::PROFILE_WHATSAPP_CHAT_CRITICALMASS => [
                'identifier' => 'https://chat.whatsapp.com/AbCdEfGhIjKlMnOpQrStUv',
                'network' => NetworkFixtures::NETWORK_WHATSAPP_CHAT,
            ],

            // Flickr / Tumblr / Google+
            self::PROFILE_FLICKR_CRITICALMASS => [
                'identifier' => 'https://www.flickr.com/photos/criticalmassberlin/',
                'network' => NetworkFixtures::NETWORK_FLICKR,
            ],
            self::PROFILE_TUMBLR_CRITICALMASS => [
                'identifier' => 'https://criticalmassberlin.tumblr.com/',
                'network' => NetworkFixtures::NETWORK_TUMBLR,
            ],
            self::PROFILE_GOOGLE_CRITICALMASS => [
                'identifier' => 'https://plus.google.com/+CriticalMassBerlin',
                'network' => NetworkFixtures::NETWORK_GOOGLE,
            ],

            // Strava
            self::PROFILE_STRAVA_ACTIVITY_CRITICALMASS => [
                'identifier' => 'https://www.strava.com/activities/1234567890',
                'network' => NetworkFixtures::NETWORK_STRAVA_ACTIVITY,
            ],
            self::PROFILE_STRAVA_CLUB_CRITICALMASS => [
                'identifier' => 'https://www.strava.com/clubs/123456',
                'network' => NetworkFixtures::NETWORK_STRAVA_CLUB,
            ],
            self::PROFILE_STRAVA_ROUTE_CRITICALMASS => [
                'identifier' => 'https://www.strava.com/routes/1234567890',
                'network' => NetworkFixtures::NETWORK_STRAVA_ROUTE,
            ],

            // YouTube
            self::PROFILE_YOUTUBE_CHANNEL_CRITICALMASS => [
                'identifier' => 'https://www.youtube.com/channel/UC1234567890abcdef',
                'network' => NetworkFixtures::NETWORK_YOUTUBE_CHANNEL,
            ],
            self::PROFILE_YOUTUBE_USER_CRITICALMASS => [
                'identifier' => 'https://www.youtube.com/user/CriticalMassBerlin',
                'network' => NetworkFixtures::NETWORK_YOUTUBE_USER,
            ],
            self::PROFILE_YOUTUBE_PLAYLIST_CRITICALMASS => [
                'identifier' => 'https://www.youtube.com/playlist?list=PL1234567890abcdef',
                'network' => NetworkFixtures::NETWORK_YOUTUBE_PLAYLIST,
            ],
            self::PROFILE_YOUTUBE_VIDEO_CRITICALMASS => [
                'identifier' => 'https://youtu.be/dQw4w9WgXcQ',
                'network' => NetworkFixtures::NETWORK_YOUTUBE_VIDEO,
            ],
        ];

        foreach ($profiles as $reference => $data) {
            $profile = new Profile();
            $profile->setId(random_int(1, 999999));
            $profile->setIdentifier($data['identifier']);
            $profile->setNetwork($this->getReference($data['network'], \App\Entity\Network::class));
            $profile->setCreatedAt(new \DateTimeImmutable());
            $profile->setAutoPublish(true);
            $profile->setAutoFetch(true);

            $manager->persist($profile);
            $this->addReference($reference, $profile);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            NetworkFixtures::class,
        ];
    }
}
