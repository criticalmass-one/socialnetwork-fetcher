<?php declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\AbstractApiTestCase;

class GroupPublicPageApiTest extends AbstractApiTestCase
{
    public function testEnablingPublicPageGeneratesSlugAndUrl(): void
    {
        $response = $this->requestAsClientA('POST', '/api/groups', [
            'json' => [
                'name' => 'Public API Group',
                'publicPageEnabled' => true,
                'publicTitle' => 'Mein Feed',
                'showTranscript' => true,
                'timeWindowDays' => 14,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();

        $this->assertTrue($data['publicPageEnabled']);
        $this->assertNotEmpty($data['publicSlug']);
        $this->assertSame('Mein Feed', $data['publicTitle']);
        $this->assertTrue($data['showTranscript']);
        $this->assertSame(14, $data['timeWindowDays']);
        $this->assertStringContainsString('/p/' . $data['publicSlug'], $data['publicUrl']);
        $this->assertFalse($data['publicPasswordProtected']);
    }

    public function testDisabledPublicPageHasNullUrl(): void
    {
        $response = $this->requestAsClientA('POST', '/api/groups', [
            'json' => ['name' => 'No Public Group'],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();

        $this->assertFalse($data['publicPageEnabled']);
        $this->assertNull($data['publicSlug']);
        $this->assertNull($data['publicUrl']);
    }

    public function testPasswordIsWriteOnlyAndNeverLeaksHash(): void
    {
        $response = $this->requestAsClientA('POST', '/api/groups', [
            'json' => [
                'name' => 'Protected Group',
                'publicPageEnabled' => true,
                'publicPassword' => 'topsecret',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();

        $this->assertTrue($data['publicPasswordProtected']);
        $this->assertArrayNotHasKey('publicPassword', $data);
        $this->assertArrayNotHasKey('publicPasswordHash', $data);
    }

    public function testPatchTogglesMediaFlag(): void
    {
        $created = $this->requestAsClientA('POST', '/api/groups', [
            'json' => ['name' => 'Toggle Group', 'showPhotos' => true],
        ])->toArray();

        $response = $this->requestWithToken('PATCH', '/api/groups/' . $created['id'], self::TOKEN_A, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'body' => json_encode(['showPhotos' => false]),
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertFalse($response->toArray()['showPhotos']);
    }

    public function testSlugIsStableAcrossUpdates(): void
    {
        $created = $this->requestAsClientA('POST', '/api/groups', [
            'json' => ['name' => 'Stable Slug', 'publicPageEnabled' => true],
        ])->toArray();
        $slug = $created['publicSlug'];
        $this->assertNotEmpty($slug);

        $updated = $this->requestAsClientA('PUT', '/api/groups/' . $created['id'], [
            'json' => ['name' => 'Stable Slug Renamed', 'publicPageEnabled' => true, 'profiles' => []],
        ])->toArray();

        $this->assertSame($slug, $updated['publicSlug']);
    }
}
