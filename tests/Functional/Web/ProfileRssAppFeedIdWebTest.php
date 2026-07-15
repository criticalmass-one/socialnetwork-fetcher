<?php declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Entity\Profile;
use App\Tests\Functional\AbstractWebTestCase;

/**
 * Admins can set a profile's RSS.app feed id by hand (to avoid the 429-prone
 * automatic API registration).
 */
class ProfileRssAppFeedIdWebTest extends AbstractWebTestCase
{
    private const PROFILE_ID = 90005; // fixture Instagram profile, feed id "fixture-feed-instagram"

    public function testSetFeedIdByHand(): void
    {
        $this->loginAsAdmin();
        $crawler = $this->client->request('GET', '/profiles/' . self::PROFILE_ID);
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action$="/rss-app-feed-id"]')->form(['rss_app_feed_id' => 'ManualFeedId123']);
        $this->client->submit($form);

        self::assertResponseRedirects('/profiles/' . self::PROFILE_ID);
        self::assertSame('ManualFeedId123', $this->reloadProfile()->getRssAppFeedId());
    }

    public function testClearFeedId(): void
    {
        $this->loginAsAdmin();
        $crawler = $this->client->request('GET', '/profiles/' . self::PROFILE_ID);

        $form = $crawler->filter('form[action$="/rss-app-feed-id"]')->form(['rss_app_feed_id' => '']);
        $this->client->submit($form);

        self::assertNull($this->reloadProfile()->getRssAppFeedId());
    }

    private function reloadProfile(): Profile
    {
        $em = $this->entityManager();
        $em->clear();

        return $em->getRepository(Profile::class)->find(self::PROFILE_ID);
    }
}
