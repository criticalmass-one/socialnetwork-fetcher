<?php declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\DataFixtures\NetworkFixtures;
use App\DataFixtures\TestFixtures;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;

abstract class AbstractApiTestCase extends ApiTestCase
{
    protected const TOKEN_A = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    protected const TOKEN_B = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    protected const TOKEN_DISABLED = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';
    protected const TOKEN_INVALID = 'dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd';

    protected static ?bool $alwaysBootKernel = false;

    protected function setUp(): void
    {
        static::bootKernel();
        $this->loadFixtures();
    }

    private function loadFixtures(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        $loader = new Loader();
        $loader->addFixture(new NetworkFixtures());
        $loader->addFixture(new TestFixtures());

        $purger = new ORMPurger($em);
        $executor = new ORMExecutor($em, $purger);
        $executor->execute($loader->getFixtures());
    }

    protected function requestWithToken(string $method, string $url, string $token, array $options = []): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        $client = static::createClient();
        $options['headers'] = array_merge(
            $options['headers'] ?? [],
            [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/ld+json',
            ],
        );

        if (isset($options['json'])) {
            $options['headers']['Content-Type'] = 'application/ld+json';
        }

        return $client->request($method, $url, $options);
    }

    protected function requestAsClientA(string $method, string $url, array $options = []): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        return $this->requestWithToken($method, $url, self::TOKEN_A, $options);
    }

    protected function requestAsClientB(string $method, string $url, array $options = []): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        return $this->requestWithToken($method, $url, self::TOKEN_B, $options);
    }
}
