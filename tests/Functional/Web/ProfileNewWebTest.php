<?php declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Entity\Profile;
use App\Repository\ProfileRepository;
use App\Tests\Functional\AbstractWebTestCase;

/**
 * The "new profile" form only needs an identifier: the network is detected
 * from it and the (non auto-increment) id is assigned on save.
 */
class ProfileNewWebTest extends AbstractWebTestCase
{
    public function testCreatingProfileDetectsNetworkAndAssignsId(): void
    {
        $identifier = 'https://mastodon.social/@brandnewprofile';

        $this->loginAsAdmin();
        $crawler = $this->client->request('GET', '/profiles/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Speichern')->form();
        $form['profile[identifier]'] = $identifier;
        // network left empty on purpose — it must be auto-detected.
        $this->client->submit($form);

        self::assertResponseRedirects();

        /** @var ProfileRepository $profiles */
        $profiles = $this->entityManager()->getRepository(Profile::class);
        $profile = $profiles->findOneBy(['identifier' => $identifier]);

        self::assertNotNull($profile, 'profile was created');
        self::assertNotNull($profile->getId(), 'id was assigned');
        self::assertNotNull($profile->getNetwork());
        self::assertSame('mastodon', $profile->getNetwork()->getIdentifier(), 'network detected from identifier');
    }

    public function testUndetectableIdentifierShowsErrorAndDoesNotSave(): void
    {
        $this->loginAsAdmin();
        $crawler = $this->client->request('GET', '/profiles/new');

        $form = $crawler->selectButton('Speichern')->form();
        $form['profile[identifier]'] = 'not-a-recognisable-url';
        $this->client->submit($form);

        // Re-renders the form with an error (422) instead of saving/redirecting.
        self::assertResponseStatusCodeSame(422);

        $profile = $this->entityManager()->getRepository(Profile::class)
            ->findOneBy(['identifier' => 'not-a-recognisable-url']);
        self::assertNull($profile, 'nothing is persisted when the network cannot be resolved');
    }
}
