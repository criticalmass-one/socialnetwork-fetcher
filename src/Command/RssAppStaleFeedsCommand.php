<?php declare(strict_types=1);

namespace App\Command;

use App\Repository\ItemRepository;
use App\Repository\ProfileRepository;
use App\RssApp\StalenessDetector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Lists RSS.app profiles that have stopped receiving new items although they
 * used to post regularly — typically because RSS.app can no longer scrape the
 * source while still reporting the feed as active.
 */
#[AsCommand(
    name: 'app:rssapp:stale-feeds',
    description: 'Listet RSS.app-Profile, die gemessen an ihrem üblichen Posting-Rhythmus auffällig lange nichts Neues geliefert haben',
)]
class RssAppStaleFeedsCommand extends Command
{
    public const DEFAULT_MAX_AGE_DAYS = 60;

    public function __construct(
        private readonly ProfileRepository $profileRepository,
        private readonly ItemRepository $itemRepository,
        private readonly StalenessDetector $stalenessDetector,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('network', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Nur diese Netzwerke (z. B. instagram_profile), mehrfach möglich')
            ->addOption('max-age', null, InputOption::VALUE_REQUIRED, 'Nur Profile, die seit höchstens so vielen Tagen still sind (ältere gelten als dauerhaft inaktiv)', (string) self::DEFAULT_MAX_AGE_DAYS)
            ->addOption('all', null, InputOption::VALUE_NONE, 'Auch Profile anzeigen, die länger als --max-age still sind')
            ->addOption('fail-on-stale', null, InputOption::VALUE_NONE, 'Mit Exit-Code 1 beenden, wenn Profile gefunden wurden (für Cron/Monitoring)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $networks = $input->getOption('network');
        $maxAgeDays = (int) $input->getOption('max-age');
        $showAll = (bool) $input->getOption('all');
        $now = new \DateTimeImmutable();

        $rows = [];

        foreach ($this->profileRepository->findWithRssAppFeedId() as $profile) {
            if (!$profile->isAutoFetch()) {
                continue;
            }

            $network = $profile->getNetwork()?->getIdentifier();

            if ($networks !== [] && !in_array($network, $networks, true)) {
                continue;
            }

            $timestamps = $this->itemRepository->findRecentTimestampsForProfile($profile, StalenessDetector::SAMPLE_SIZE);
            $stale = $this->stalenessDetector->detect($timestamps, $now);

            if ($stale === null || (!$showAll && $stale->ageDays > $maxAgeDays)) {
                continue;
            }

            $rows[] = [
                'age' => $stale->ageDays,
                'row' => [
                    $profile->getId(),
                    $network,
                    $profile->getIdentifier(),
                    $stale->newestItem->format('Y-m-d'),
                    sprintf('%.1f', $stale->ageDays),
                    sprintf('%.1f', $stale->medianGapDays),
                    $profile->getRssAppFeedId(),
                ],
            ];
        }

        if ($rows === []) {
            $io->success('Keine auffällig stillen RSS.app-Feeds gefunden.');

            return Command::SUCCESS;
        }

        usort($rows, static fn (array $a, array $b): int => $a['age'] <=> $b['age']);

        $io->table(
            ['ID', 'Netzwerk', 'Identifier', 'Neuester Beitrag', 'Still seit (Tage)', 'Üblicher Abstand (Tage)', 'RSS.app-Feed'],
            array_column($rows, 'row'),
        );

        $io->note(sprintf(
            '%d Profil(e). Kriterium: neuester Beitrag älter als %d × üblicher Abstand und mindestens %d Tage.%s Feed prüfen unter https://rss.app/r/feed/<Feed-ID>.',
            count($rows),
            StalenessDetector::FACTOR,
            StalenessDetector::MIN_DAYS,
            $showAll ? '' : sprintf(' Profile, die länger als %d Tage still sind, sind ausgeblendet (--all).', $maxAgeDays),
        ));

        return $input->getOption('fail-on-stale') ? Command::FAILURE : Command::SUCCESS;
    }
}
