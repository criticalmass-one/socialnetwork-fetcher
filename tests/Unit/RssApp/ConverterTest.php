<?php declare(strict_types=1);

namespace App\Tests\Unit\RssApp;

use App\Model\Profile;
use App\RssApp\Converter;
use PHPUnit\Framework\TestCase;

class ConverterTest extends TestCase
{
    public function testConvertsItem(): void
    {
        $item = Converter::convert($this->makeProfile(), [
            'url' => 'https://www.facebook.com/cmhamburg/posts/1',
            'title' => 'Titel',
            'description_text' => 'Text',
            'date_published' => '2026-10-22T15:30:07.000Z',
        ]);

        self::assertNotNull($item);
        self::assertSame('https://www.facebook.com/cmhamburg/posts/1', $item->getUniqueIdentifier());
        self::assertSame('Text', $item->getText());
        self::assertSame('2026-10-22 15:30:07', $item->getDateTime()->format('Y-m-d H:i:s'));
    }

    public function testSkipsItemWithoutDate(): void
    {
        // Observed for Facebook's login wall and old tweets: RSS.app omits date_published.
        $item = Converter::convert($this->makeProfile(), [
            'url' => 'https://www.facebook.com/r.php?next=https%3A%2F%2Fwww.facebook.com%2Fcmhamburg',
            'title' => 'Sign up for Facebook',
            'description_text' => '',
        ]);

        self::assertNull($item);
    }

    public function testSkipsItemWithoutUrl(): void
    {
        $item = Converter::convert($this->makeProfile(), [
            'title' => 'Titel',
            'date_published' => '2026-10-22T15:30:07.000Z',
        ]);

        self::assertNull($item);
    }

    private function makeProfile(): Profile
    {
        $profile = new Profile();
        $profile
            ->setId(42)
            ->setIdentifier('https://www.facebook.com/cmhamburg')
            ->setNetwork('facebook_page');

        return $profile;
    }
}
