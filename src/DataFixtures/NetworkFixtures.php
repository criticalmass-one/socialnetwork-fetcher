<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Network;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class NetworkFixtures extends Fixture
{
    public const NETWORK_HOMEPAGE = 'network-homepage';

    public const NETWORK_FACEBOOK_PROFILE = 'network-facebook-profile';
    public const NETWORK_FACEBOOK_GROUP   = 'network-facebook-group';
    public const NETWORK_FACEBOOK_EVENT   = 'network-facebook-event';
    public const NETWORK_FACEBOOK_PAGE    = 'network-facebook-page';

    public const NETWORK_INSTAGRAM_PROFILE = 'network-instagram-profile';
    public const NETWORK_INSTAGRAM_PHOTO   = 'network-instagram-photo';

    public const NETWORK_TWITTER = 'network-twitter';

    public const NETWORK_MASTODON = 'network-mastodon';

    public const NETWORK_BLUESKY_PROFILE = 'network-bluesky-profile';

    public const NETWORK_THREADS_PROFILE = 'network-threads-profile';
    public const NETWORK_THREADS_POST    = 'network-threads-post';

    public const NETWORK_DISCORD_CHAT  = 'network-discord-chat';
    public const NETWORK_TELEGRAM_CHAT = 'network-telegram-chat';
    public const NETWORK_WHATSAPP_CHAT = 'network-whatsapp-chat';

    public const NETWORK_FLICKR = 'network-flickr';
    public const NETWORK_TUMBLR = 'network-tumblr';
    public const NETWORK_GOOGLE = 'network-google';

    public const NETWORK_STRAVA_ACTIVITY = 'network-strava-activity';
    public const NETWORK_STRAVA_CLUB     = 'network-strava-club';
    public const NETWORK_STRAVA_ROUTE    = 'network-strava-route';

    public const NETWORK_YOUTUBE_CHANNEL  = 'network-youtube-channel';
    public const NETWORK_YOUTUBE_USER     = 'network-youtube-user';
    public const NETWORK_YOUTUBE_PLAYLIST = 'network-youtube-playlist';
    public const NETWORK_YOUTUBE_VIDEO    = 'network-youtube-video';

    public function load(ObjectManager $manager): void
    {
        $networks = [
            // --- Homepage ---
            self::NETWORK_HOMEPAGE => [
                'identifier' => 'homepage',
                'name' => 'Homepage',
                'icon' => 'far fa-home',
                'backgroundColor' => 'white',
                'textColor' => 'black',
                // Altcode: filter_var(URL) – wir approximieren das als "muss URL sein"
                'profileUrlPattern' => '#^https?://.+$#i',
            ],

            // --- Facebook (AbstractFacebookNetwork + Spezialisierungen) ---
            self::NETWORK_FACEBOOK_PROFILE => [
                'identifier' => 'facebook_profile',
                'name' => 'Facebook-Profil',
                'icon' => 'fab fa-facebook-f',
                'backgroundColor' => 'rgb(60, 88, 152)',
                'textColor' => 'white',
                // Altcode: FacebookProfile prüft zusätzlich auf profile.php?id=
                'profileUrlPattern' => '#^https?://(www\.)?facebook\.com/profile\.php\?id=\d+.*$#i',
            ],
            self::NETWORK_FACEBOOK_GROUP => [
                'identifier' => 'facebook_group',
                'name' => 'Facebook-Gruppe',
                'icon' => 'fab fa-facebook-f',
                'backgroundColor' => 'rgb(60, 88, 152)',
                'textColor' => 'white',
                'profileUrlPattern' => '#^https?://(www\.)?facebook\.com/groups/[^/?#]+/?$#i',
            ],
            self::NETWORK_FACEBOOK_EVENT => [
                'identifier' => 'facebook_event',
                'name' => 'Facebook-Event',
                'icon' => 'fab fa-facebook-f',
                'backgroundColor' => 'rgb(60, 88, 152)',
                'textColor' => 'white',
                'profileUrlPattern' => '#^https?://(www\.)?facebook\.com/events/[^/?#]+/?$#i',
            ],
            self::NETWORK_FACEBOOK_PAGE => [
                'identifier' => 'facebook_page',
                'name' => 'Facebook-Seite',
                'icon' => 'fab fa-facebook-f',
                'backgroundColor' => 'rgb(60, 88, 152)',
                'textColor' => 'white',
                // Hinweis: Altcode FacebookPage::accepts war unvollständig (return false).
                // Hier eine sinnvolle Annäherung: facebook.com/<slug> ohne groups/events/profile.php
                'profileUrlPattern' => '#^https?://(www\.)?facebook\.com/(?!groups/|events/|profile\.php)([A-Za-z0-9.\-]+)/?$#i',
            ],

            // --- Instagram (AbstractInstagramNetwork + Profile/Photo) ---
            self::NETWORK_INSTAGRAM_PROFILE => [
                'identifier' => 'instagram_profile',
                'name' => 'Instagram-Profil',
                'icon' => 'fab fa-instagram',
                'backgroundColor' => 'rgb(203, 44, 128)',
                'textColor' => 'white',
                'profileUrlPattern' => '#^https?://(www\.)?instagram\.[A-Za-z]{2,3}/[A-Za-z0-9\-_]{5,}/?$#i',
            ],
            self::NETWORK_INSTAGRAM_PHOTO => [
                'identifier' => 'instagram_photo',
                'name' => 'Instagram-Foto',
                'icon' => 'fab fa-instagram',
                'backgroundColor' => 'rgb(203, 44, 128)',
                'textColor' => 'white',
                'profileUrlPattern' => '#^https?://(www\.)?instagram\.com/p/[^/?#]+/?$#i',
            ],

            // --- Twitter (Altklasse: Twitter) ---
            self::NETWORK_TWITTER => [
                'identifier' => 'twitter',
                'name' => 'Twitter',
                'icon' => 'fab fa-twitter',
                'backgroundColor' => 'rgb(29, 161, 242)',
                'textColor' => 'white',
                // Altcode: twitter.com/<handle> (2 matches). Zusätzlich x.com zulassen.
                'profileUrlPattern' => '#^https?://(www\.)?(twitter|x)\.com/[A-Za-z0-9_]+/?$#i',
            ],

            // --- Mastodon (Altklasse: Mastodon) ---
            self::NETWORK_MASTODON => [
                'identifier' => 'mastodon',
                'name' => 'Mastodon',
                'icon' => 'fab fa-mastodon',
                'backgroundColor' => 'rgb(96, 94, 239)',
                'textColor' => 'white',
                // Altcode akzeptiert auch "user@domain" (kein URL) -> wir erlauben beides
                'profileUrlPattern' => '#^(@?[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}|https?://[A-Za-z0-9.\-]+/@[A-Za-z0-9_]+/?$)#i',
            ],

            // --- Bluesky (Altklasse: BlueskyProfile) ---
            self::NETWORK_BLUESKY_PROFILE => [
                'identifier' => 'bluesky_profile',
                'name' => 'Bluesky-Profil',
                'icon' => 'fab fa-bluesky',
                'backgroundColor' => '#0276ff',
                'textColor' => 'white',
                // Altcode: entweder bsky.app/profile/did:plc:... ODER nur handle-domain (foo.bar)
                'profileUrlPattern' => '#^(https?://bsky\.app/profile/(did:plc:[a-z0-9]+|[a-z0-9.\-]+\.[a-z]{2,})/?|[a-z0-9.\-]+\.[a-z]{2,})$#i',
            ],

            // --- Threads (Altklassen: ThreadsProfile/ThreadsPost) ---
            self::NETWORK_THREADS_PROFILE => [
                'identifier' => 'threads_profile',
                'name' => 'Threads-Profil',
                'icon' => 'fab fa-threads',
                'backgroundColor' => '#000000',
                'textColor' => 'white',
                'profileUrlPattern' => '#^https?://(www\.)?threads\.(net|com)/@[\w.]+/?$#i',
            ],
            self::NETWORK_THREADS_POST => [
                'identifier' => 'threads_post',
                'name' => 'Threads-Beitrag',
                'icon' => 'fab fa-threads',
                'backgroundColor' => '#000000',
                'textColor' => 'white',
                'profileUrlPattern' => '#^https?://(www\.)?threads\.(net|com)/@[\w.]+/post/[0-9]+/?$#i',
            ],

            // --- Chats (Altklassen: DiscordChat/TelegramChat/WhatsappChat) ---
            self::NETWORK_DISCORD_CHAT => [
                'identifier' => 'discord_chat',
                'name' => 'Discord-Chat',
                'icon' => 'fab fa-discord',
                'backgroundColor' => 'rgb(114, 137, 218)',
                'textColor' => 'white',
                'profileUrlPattern' => '#^https?://discord(app\.com/|\.gg/).+$#i',
            ],
            self::NETWORK_TELEGRAM_CHAT => [
                'identifier' => 'telegram_chat',
                'name' => 'Telegram-Chat',
                'icon' => 'fab fa-telegram-plane',
                'backgroundColor' => 'rgb(40, 159, 217)',
                'textColor' => 'white',
                'profileUrlPattern' => '#^https?://t\.me/.+$#i',
            ],
            self::NETWORK_WHATSAPP_CHAT => [
                'identifier' => 'whatsapp_chat',
                'name' => 'WhatsApp-Chat',
                'icon' => 'fab fa-whatsapp',
                'backgroundColor' => 'rgb(65, 193, 83)',
                'textColor' => 'white',
                'profileUrlPattern' => '#^https?://chat\.whatsapp\.com/.+$#i',
            ],

            // --- Sonstiges (Altklassen: Flickr/Tumblr/Google) ---
            self::NETWORK_FLICKR => [
                'identifier' => 'flickr',
                'name' => 'flickr',
                'icon' => 'fab fa-flickr',
                'backgroundColor' => 'rgb(12, 101, 211)',
                'textColor' => 'white',
                'profileUrlPattern' => '#^https?://(www\.)?flickr\.com/photos/.+$#i',
            ],
            self::NETWORK_TUMBLR => [
                'identifier' => 'tumblr',
                'name' => 'Tumblr',
                'icon' => 'fab fa-tumblr',
                'backgroundColor' => 'rgb(0, 0, 0)',
                'textColor' => 'white',
                'profileUrlPattern' => '#^https?://(www\.)?[A-Za-z0-9]*\.tumblr\.com/?$#i',
            ],
            self::NETWORK_GOOGLE => [
                'identifier' => 'google',
                'name' => 'Google+',
                'icon' => 'fab fa-google-plus-g',
                'backgroundColor' => 'rgb(234, 66, 53)',
                'textColor' => 'white',
                'profileUrlPattern' => '#^https?://(www\.)?plus\.google\.com/\+[A-Za-z0-9\-]+/?$#i',
            ],

            // --- Strava (Altklassen: StravaActivity/StravaClub/StravaRoute) ---
            self::NETWORK_STRAVA_ACTIVITY => [
                'identifier' => 'strava_activity',
                'name' => 'Strava-Aktivität',
                'icon' => 'fab fa-strava',
                'backgroundColor' => 'rgb(252, 82, 0)',
                'textColor' => 'white',
                'profileUrlPattern' => '#^https?://(www\.)?strava\.com/activities/\d+/?$#i',
            ],
            self::NETWORK_STRAVA_CLUB => [
                'identifier' => 'strava_club',
                'name' => 'Strava-Club',
                'icon' => 'fab fa-strava',
                'backgroundColor' => 'rgb(252, 82, 0)',
                'textColor' => 'white',
                'profileUrlPattern' => '#^https?://(www\.)?strava\.com/clubs/\d+/?$#i',
            ],
            self::NETWORK_STRAVA_ROUTE => [
                'identifier' => 'strava_route',
                'name' => 'Strava-Route',
                'icon' => 'fab fa-strava',
                'backgroundColor' => 'rgb(252, 82, 0)',
                'textColor' => 'white',
                'profileUrlPattern' => '#^https?://(www\.)?strava\.com/routes/\d+/?$#i',
            ],

            // --- YouTube (Altklassen: YoutubeChannel/YoutubeUser/YoutubePlaylist/YoutubeVideo) ---
            self::NETWORK_YOUTUBE_CHANNEL => [
                'identifier' => 'youtube_channel',
                'name' => 'YouTube',
                'icon' => 'fab fa-youtube',
                'backgroundColor' => 'rgb(255, 0, 0)',
                'textColor' => 'white',
                'profileUrlPattern' => '#^https?://(www\.)?youtube\.com/channel/.+$#i',
            ],
            self::NETWORK_YOUTUBE_USER => [
                'identifier' => 'youtube_user',
                'name' => 'YouTube-Konto',
                'icon' => 'fab fa-youtube',
                'backgroundColor' => 'rgb(255, 0, 0)',
                'textColor' => 'white',
                'profileUrlPattern' => '#^https?://(www\.)?youtube\.com/user/.+$#i',
            ],
            self::NETWORK_YOUTUBE_PLAYLIST => [
                'identifier' => 'youtube_playlist',
                'name' => 'YouTube-Playlist',
                'icon' => 'fab fa-youtube',
                'backgroundColor' => 'rgb(255, 0, 0)',
                'textColor' => 'white',
                'profileUrlPattern' => '#^https?://(www\.)?youtube\.com/playlist\?.+$#i',
            ],
            self::NETWORK_YOUTUBE_VIDEO => [
                'identifier' => 'youtube_video',
                'name' => 'YouTube-Video',
                'icon' => 'fab fa-youtube',
                'backgroundColor' => 'rgb(255, 0, 0)',
                'textColor' => 'white',
                // Altcode akzeptiert sehr viel (youtube.com und youtu.be)
                'profileUrlPattern' => '#^((?:https?:)?//)?((?:www|m)\.)?((?:youtube\.com|youtu\.be))(\/(?:watch+\?v=|embed\/|v\/)?)([\w\-]+)(\S+)?$#i',
            ],
        ];

        foreach ($networks as $reference => $data) {
            $network = new Network();
            $network->setIdentifier($data['identifier']);
            $network->setName($data['name']);
            $network->setIcon($data['icon']);
            $network->setBackgroundColor($data['backgroundColor']);
            $network->setTextColor($data['textColor']);
            $network->setProfileUrlPattern($data['profileUrlPattern']);

            $manager->persist($network);
            $this->addReference($reference, $network);
        }

        $manager->flush();
    }
}
