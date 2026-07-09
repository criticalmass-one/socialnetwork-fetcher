<?php declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Entity\Profile;
use App\Tests\Functional\AbstractWebTestCase;

/**
 * Surface (browser) tests for the admin Web-UI "Identifier ändern" flow on the
 * profile detail page: rendering, the happy path, every validation branch, the
 * RSS.app re-link path, CSRF protection and authentication.
 */
class ProfileChangeIdentifierWebTest extends AbstractWebTestCase
{
    private const MASTODON_ID = 90001;                       // https://mastodon.social/@shared (non-RSS)
    private const MASTODON_IDENTIFIER = 'https://mastodon.social/@shared';
    private const INSTAGRAM_ID = 90005;                      // https://www.instagram.com/oldname/ (RSS.app)
    private const INSTAGRAM_IDENTIFIER = 'https://www.instagram.com/oldname/';

    public function testShowPageRendersChangeIdentifierCardWithCurrentIdentifier(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', '/profiles/' . self::MASTODON_ID);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Identifier ändern');

        $input = $crawler->filter($this->formSelector(self::MASTODON_ID) . ' input[name="identifier"]');
        self::assertCount(1, $input, 'The change-identifier form input should be rendered.');
        self::assertSame(self::MASTODON_IDENTIFIER, $input->attr('value'));
    }

    public function testChangeIdentifierSuccessOnNonRssNetwork(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->submitIdentifierChange(self::MASTODON_ID, 'https://mastodon.social/@renamed');

        self::assertSelectorExists('.alert-success');
        self::assertSelectorTextContains('.alert', 'Identifier wurde aktualisiert.');
        self::assertSame('https://mastodon.social/@renamed', $this->renderedIdentifier($crawler, self::MASTODON_ID));
    }

    public function testChangeIdentifierRejectsInvalidValue(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->submitIdentifierChange(self::MASTODON_ID, 'not-a-valid-mastodon-handle');

        self::assertSelectorExists('.alert-danger');
        self::assertSelectorTextContains('.alert', 'kein gültiger Identifier');
        self::assertSame(self::MASTODON_IDENTIFIER, $this->renderedIdentifier($crawler, self::MASTODON_ID));
    }

    public function testChangeIdentifierRejectsEmptyValue(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->submitIdentifierChange(self::MASTODON_ID, '   ');

        self::assertSelectorExists('.alert-danger');
        self::assertSelectorTextContains('.alert', 'darf nicht leer sein');
        self::assertSame(self::MASTODON_IDENTIFIER, $this->renderedIdentifier($crawler, self::MASTODON_ID));
    }

    public function testChangeIdentifierRejectsDuplicateInSameNetwork(): void
    {
        $this->loginAsAdmin();

        // @onlyb already exists as profile 90003 on the mastodon network.
        $crawler = $this->submitIdentifierChange(self::MASTODON_ID, 'https://mastodon.social/@onlyb');

        self::assertSelectorExists('.alert-danger');
        self::assertSelectorTextContains('.alert', 'existiert bereits');
        self::assertSame(self::MASTODON_IDENTIFIER, $this->renderedIdentifier($crawler, self::MASTODON_ID));
    }

    public function testChangeIdentifierUnchangedReportsNoChange(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->submitIdentifierChange(self::MASTODON_ID, self::MASTODON_IDENTIFIER);

        self::assertSelectorTextContains('.alert', 'Identifier unverändert.');
        self::assertSame(self::MASTODON_IDENTIFIER, $this->renderedIdentifier($crawler, self::MASTODON_ID));
    }

    public function testChangeIdentifierRelinksRssAppFeed(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->submitIdentifierChange(self::INSTAGRAM_ID, 'https://www.instagram.com/newname/');

        self::assertSelectorExists('.alert-success');
        self::assertSelectorTextContains('.alert', 'neuer RSS.app-Feed');
        self::assertSame('https://www.instagram.com/newname/', $this->renderedIdentifier($crawler, self::INSTAGRAM_ID));

        // The stub RSS.app client creates a fresh feed ("null-feed-id"); the old
        // fixture feed id must have been replaced.
        $this->entityManager()->clear();
        $profile = $this->entityManager()->getRepository(Profile::class)->find(self::INSTAGRAM_ID);
        self::assertNotNull($profile);
        self::assertSame('null-feed-id', $profile->getRssAppFeedId());
    }

    public function testChangeIdentifierWithInvalidCsrfTokenIsIgnored(): void
    {
        $this->loginAsAdmin();

        $this->client->request('POST', '/profiles/' . self::MASTODON_ID . '/change-identifier', [
            'identifier' => 'https://mastodon.social/@hacked',
            '_token' => 'invalid-token',
        ]);

        self::assertResponseRedirects('/profiles/' . self::MASTODON_ID);
        $crawler = $this->client->followRedirect();

        self::assertSelectorNotExists('.alert-success');
        self::assertSame(self::MASTODON_IDENTIFIER, $this->renderedIdentifier($crawler, self::MASTODON_ID));
    }

    public function testChangeIdentifierRequiresAdminAuthentication(): void
    {
        // Not logged in — the main firewall must bounce to the login form.
        $this->client->request('POST', '/profiles/' . self::MASTODON_ID . '/change-identifier', [
            'identifier' => 'https://mastodon.social/@anon',
            '_token' => 'whatever',
        ]);

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));

        // And the identifier is untouched.
        $this->entityManager()->clear();
        $profile = $this->entityManager()->getRepository(Profile::class)->find(self::MASTODON_ID);
        self::assertSame(self::MASTODON_IDENTIFIER, $profile?->getIdentifier());
    }

    /**
     * Loads the profile page, fills the "Identifier ändern" form (which carries a
     * valid CSRF token) with $newIdentifier, submits it and follows the redirect
     * back to the show page. Returns the resulting crawler.
     */
    private function submitIdentifierChange(int $profileId, string $newIdentifier): \Symfony\Component\DomCrawler\Crawler
    {
        $crawler = $this->client->request('GET', '/profiles/' . $profileId);
        self::assertResponseIsSuccessful();

        $form = $crawler->filter($this->formSelector($profileId))->form();
        $form['identifier'] = $newIdentifier;
        $this->client->submit($form);

        self::assertResponseRedirects('/profiles/' . $profileId);

        return $this->client->followRedirect();
    }

    private function renderedIdentifier(\Symfony\Component\DomCrawler\Crawler $crawler, int $profileId): string
    {
        return $crawler->filter($this->formSelector($profileId) . ' input[name="identifier"]')->attr('value');
    }

    private function formSelector(int $profileId): string
    {
        return sprintf('form[action="/profiles/%d/change-identifier"]', $profileId);
    }
}
