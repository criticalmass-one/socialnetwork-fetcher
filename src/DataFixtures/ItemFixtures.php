<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Item;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ItemFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $profiles = $this->getAllCriticalmassProfileReferences();

        $sampleTexts = [
            'Nächste Critical Mass am letzten Freitag des Monats! Kommt alle vorbei! 🚲',
            'Was für eine tolle Tour gestern! Über 500 Radfahrer waren dabei. Danke an alle!',
            'Reminder: Helm nicht vergessen und Lichter checken! Safety first! 🔦',
            'Die Route für diesen Monat steht fest. Start wie immer am Mariannenplatz.',
            'Tolles Wetter heute - perfekt für eine Radtour durch die Stadt!',
            'Danke an die Polizei für die Begleitung heute Abend!',
            'Neue Fahrradwege in der Innenstadt - ein kleiner Schritt in die richtige Richtung.',
            'Critical Mass ist keine Demo, sondern eine Fahrradtour mit Freunden!',
            'Wir fahren bei jedem Wetter - heute wieder mit Regenjacke!',
            'Schöne Grüße aus der Critical Mass! Bis zum nächsten Mal!',
        ];

        $itemsPerProfile = 5;

        foreach ($profiles as $profileReference) {
            $profile = $this->getReference($profileReference, \App\Entity\Profile::class);

            for ($i = 0; $i < $itemsPerProfile; $i++) {
                $item = new Item();
                $item->setProfile($profile);
                $item->setUniqueIdentifier(sprintf('%s-%d', $profileReference, $i));
                $item->setText($sampleTexts[array_rand($sampleTexts)]);
                $item->setDateTime(new \DateTimeImmutable(sprintf('-%d days', random_int(1, 90))));
                $item->setPermalink(sprintf('https://example.com/post/%s', uniqid('', true)));
                $item->setTitle(null);
                $item->setHidden(false);
                $item->setDeleted(false);

                $manager->persist($item);
            }
        }

        $manager->flush();
    }

    /**
     * Sammelt automatisch alle ProfileFixtures::PROFILE_*_CRITICALMASS Konstanten ein.
     *
     * @return array<int, string>
     */
    private function getAllCriticalmassProfileReferences(): array
    {
        $reflection = new \ReflectionClass(ProfileFixtures::class);
        $constants = $reflection->getConstants();

        $profiles = [];

        foreach ($constants as $name => $value) {
            if (0 === strpos($name, 'PROFILE_') && str_ends_with($name, '_CRITICALMASS')) {
                $profiles[] = $value;
            }
        }

        sort($profiles);

        return $profiles;
    }

    public function getDependencies(): array
    {
        return [
            ProfileFixtures::class,
        ];
    }
}
