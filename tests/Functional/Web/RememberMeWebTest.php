<?php declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Tests\Functional\AbstractWebTestCase;

/**
 * The admin form login offers "Angemeldet bleiben"; when checked, the firewall
 * must issue a persistent REMEMBERME cookie so the session survives a browser
 * restart.
 */
class RememberMeWebTest extends AbstractWebTestCase
{
    public function testLoginWithRememberMeSetsCookie(): void
    {
        $this->submitLogin(rememberMe: true);

        self::assertResponseRedirects();
        self::assertNotNull(
            $this->client->getCookieJar()->get('REMEMBERME'),
            'a remember-me cookie is issued when the box is checked',
        );
    }

    public function testLoginWithoutRememberMeSetsNoCookie(): void
    {
        $this->submitLogin(rememberMe: false);

        self::assertResponseRedirects();
        self::assertNull($this->client->getCookieJar()->get('REMEMBERME'));
    }

    private function submitLogin(bool $rememberMe): void
    {
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Sign in')->form([
            '_username' => 'admin',
            '_password' => 'adminpass',
        ]);

        if ($rememberMe) {
            $form['_remember_me']->tick();
        } else {
            $form['_remember_me']->untick();
        }

        $this->client->submit($form);
    }
}
