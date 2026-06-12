<?php declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\DataFixtures\NetworkFixtures;
use App\DataFixtures\TestFixtures;
use App\Entity\Client;
use App\Entity\Group;
use App\Entity\Profile;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use App\Security\WebUserProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GroupManagementTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');

        $loader = new Loader();
        $loader->addFixture(new NetworkFixtures());
        $loader->addFixture(new TestFixtures());

        $executor = new ORMExecutor($this->em, new ORMPurger($this->em));
        $executor->execute($loader->getFixtures());

        // Use the provider's own user so the refreshed user matches exactly —
        // a hand-built InMemoryUser with a different password hash would be
        // deauthenticated by the usersChanged check on the next request.
        $admin = static::getContainer()->get(WebUserProvider::class)->loadUserByIdentifier('admin');
        $this->client->loginUser($admin, 'main');
    }

    private function findClient(string $name): Client
    {
        return $this->em->getRepository(Client::class)->findOneBy(['name' => $name]);
    }

    private function findProfile(string $identifier): Profile
    {
        return $this->em->getRepository(Profile::class)->findOneBy(['identifier' => $identifier]);
    }

    private function createGroup(string $name, Client $client): Group
    {
        $group = new Group();
        $group->setName($name);
        $group->setClient($client);
        $this->em->persist($group);
        $this->em->flush();

        return $group;
    }

    public function testCreateGroupViaForm(): void
    {
        $crawler = $this->client->request('GET', '/groups/new');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Anlegen')->form([
            'group[name]' => 'Klimagruppen',
            'group[client]' => (string) $this->findClient('Client A')->getId(),
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects();

        $group = $this->em->getRepository(Group::class)->findOneBy(['name' => 'Klimagruppen']);
        $this->assertNotNull($group);
        $this->assertSame('Client A', $group->getClient()->getName());
    }

    public function testCreateGroupWithEmptyNameShowsFormErrorInsteadOf500(): void
    {
        $crawler = $this->client->request('GET', '/groups/new');
        $form = $crawler->selectButton('Anlegen')->form([
            'group[name]' => '',
            'group[client]' => (string) $this->findClient('Client A')->getId(),
        ]);
        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('Bitte einen Namen', $this->client->getResponse()->getContent());
    }

    public function testCreateGroupWithDuplicateNameShowsFormErrorInsteadOf500(): void
    {
        $clientA = $this->findClient('Client A');
        $this->createGroup('Doppelt', $clientA);

        $crawler = $this->client->request('GET', '/groups/new');
        $form = $crawler->selectButton('Anlegen')->form([
            'group[name]' => 'Doppelt',
            'group[client]' => (string) $clientA->getId(),
        ]);
        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('existiert für diesen Client bereits', $this->client->getResponse()->getContent());
    }

    public function testSameNameForDifferentClientIsAllowed(): void
    {
        $this->createGroup('Geteilt', $this->findClient('Client A'));

        $crawler = $this->client->request('GET', '/groups/new');
        $form = $crawler->selectButton('Anlegen')->form([
            'group[name]' => 'Geteilt',
            'group[client]' => (string) $this->findClient('Client B')->getId(),
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects();
        $this->assertCount(2, $this->em->getRepository(Group::class)->findBy(['name' => 'Geteilt']));
    }

    public function testAdminCanAddUnlinkedProfileViaGroupPage(): void
    {
        // Group belongs to Client A; profile onlyB is linked to Client B only.
        $group = $this->createGroup('Admin-Gruppe', $this->findClient('Client A'));
        $profile = $this->findProfile('https://mastodon.social/@onlyb');

        $crawler = $this->client->request('GET', '/groups/' . $group->getId());
        $this->assertResponseIsSuccessful();

        $token = $crawler->filter('form[action$="/profiles/add"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/groups/' . $group->getId() . '/profiles/add', [
            '_token' => $token,
            'profileIds' => [(string) $profile->getId()],
        ]);

        $this->assertResponseRedirects('/groups/' . $group->getId());

        $this->em->clear();
        $reloaded = $this->em->getRepository(Group::class)->find($group->getId());
        $this->assertSame(1, $reloaded->getProfileCount());
    }

    public function testAddProfileSkipsDeletedProfiles(): void
    {
        $group = $this->createGroup('Keine-Leichen', $this->findClient('Client A'));
        $deleted = $this->findProfile('https://mastodon.social/@deleted');

        $crawler = $this->client->request('GET', '/groups/' . $group->getId());
        $token = $crawler->filter('form[action$="/profiles/add"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/groups/' . $group->getId() . '/profiles/add', [
            '_token' => $token,
            'profileIds' => [(string) $deleted->getId()],
        ]);

        $this->assertResponseRedirects();

        $this->em->clear();
        $reloaded = $this->em->getRepository(Group::class)->find($group->getId());
        $this->assertSame(0, $reloaded->getProfileCount());
    }

    public function testRenameGroupContainingUnlinkedMember(): void
    {
        // Regression: previously the edit form re-validated client linkage of
        // all members and made such groups uneditable (deadlock).
        $group = $this->createGroup('Vorher', $this->findClient('Client A'));
        $group->addProfile($this->findProfile('https://mastodon.social/@onlyb'));
        $this->em->flush();

        $crawler = $this->client->request('GET', '/groups/' . $group->getId() . '/edit');
        $form = $crawler->selectButton('Speichern')->form([
            'group[name]' => 'Nachher',
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects();

        $this->em->clear();
        $reloaded = $this->em->getRepository(Group::class)->find($group->getId());
        $this->assertSame('Nachher', $reloaded->getName());
        $this->assertSame(1, $reloaded->getProfileCount(), 'Mitglieder dürfen beim Umbenennen nicht verloren gehen');
    }

    public function testProfileSearchExcludesMembersAndDeleted(): void
    {
        $group = $this->createGroup('Suche', $this->findClient('Client A'));
        $member = $this->findProfile('https://mastodon.social/@shared');
        $group->addProfile($member);
        $this->em->flush();

        $this->client->request('GET', '/groups/' . $group->getId() . '/profiles/search', ['q' => 'mastodon']);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $ids = array_column($data['results'], 'id');
        $this->assertNotContains($member->getId(), $ids, 'Mitglieder dürfen nicht erneut angeboten werden');

        $identifiers = array_column($data['results'], 'identifier');
        $this->assertContains('https://mastodon.social/@onlyb', $identifiers);
        $this->assertNotContains('https://mastodon.social/@deleted', $identifiers, 'Gelöschte Profile dürfen nicht angeboten werden');
    }

    public function testAddProfileWithInvalidCsrfShowsErrorFlash(): void
    {
        $group = $this->createGroup('CSRF-Gruppe', $this->findClient('Client A'));
        $profile = $this->findProfile('https://mastodon.social/@onlyb');

        $this->client->request('POST', '/groups/' . $group->getId() . '/profiles/add', [
            '_token' => 'kaputt',
            'profileIds' => [(string) $profile->getId()],
        ]);

        $this->assertResponseRedirects();
        $crawler = $this->client->followRedirect();

        $this->assertStringContainsString('ungültiges Sicherheitstoken', $crawler->html());

        $this->em->clear();
        $reloaded = $this->em->getRepository(Group::class)->find($group->getId());
        $this->assertSame(0, $reloaded->getProfileCount());
    }
}
