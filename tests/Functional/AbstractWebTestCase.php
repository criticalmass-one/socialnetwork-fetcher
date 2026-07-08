<?php declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\NetworkFixtures;
use App\DataFixtures\TestFixtures;
use App\Security\WebUserProvider;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base class for admin Web-UI (browser) functional tests. Boots a KernelBrowser,
 * loads the shared test fixtures, and offers a helper to authenticate as the
 * in-memory admin (ROLE_ADMIN) that guards the `/` firewall.
 */
abstract class AbstractWebTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->loadFixtures();
    }

    protected function loginAsAdmin(): void
    {
        // Load the exact user the WebUserProvider will return on refresh — a
        // hand-built InMemoryUser with a different password would be treated as
        // "user changed" and deauthenticated on the next request.
        /** @var WebUserProvider $provider */
        $provider = static::getContainer()->get(WebUserProvider::class);
        $this->client->loginUser($provider->loadUserByIdentifier('admin'), 'main');
    }

    protected function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        return $em;
    }

    private function loadFixtures(): void
    {
        $loader = new Loader();
        $loader->addFixture(new NetworkFixtures());
        $loader->addFixture(new TestFixtures());

        $purger = new ORMPurger($this->entityManager());
        $executor = new ORMExecutor($this->entityManager(), $purger);
        $executor->execute($loader->getFixtures());
    }
}
