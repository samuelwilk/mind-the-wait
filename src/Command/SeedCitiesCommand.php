<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\City;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function sprintf;

#[AsCommand(
    name: 'app:seed:cities',
    description: 'Seed Canadian cities for iOS app multi-city support'
)]
final class SeedCitiesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $cities = [
            [
                'name'       => 'Regina',
                'slug'       => 'regina',
                'country'    => 'CA',
                'center_lat' => '50.4452',
                'center_lon' => '-104.6189',
            ],
            [
                'name'       => 'Winnipeg',
                'slug'       => 'winnipeg',
                'country'    => 'CA',
                'center_lat' => '49.8951',
                'center_lon' => '-97.1384',
            ],
            [
                'name'       => 'Calgary',
                'slug'       => 'calgary',
                'country'    => 'CA',
                'center_lat' => '51.0447',
                'center_lon' => '-114.0719',
            ],
            [
                'name'       => 'Edmonton',
                'slug'       => 'edmonton',
                'country'    => 'CA',
                'center_lat' => '53.5461',
                'center_lon' => '-113.4938',
            ],
        ];

        $seededCount = 0;

        foreach ($cities as $cityData) {
            // Check if city already exists
            $existingCity = $this->em->getRepository(City::class)->findOneBy(['slug' => $cityData['slug']]);

            if ($existingCity !== null) {
                $io->warning(sprintf('City "%s" already exists, skipping.', $cityData['name']));
                continue;
            }

            $city = new City();
            $city->setName($cityData['name']);
            $city->setSlug($cityData['slug']);
            $city->setCountry($cityData['country']);
            $city->setCenterLat($cityData['center_lat']);
            $city->setCenterLon($cityData['center_lon']);
            $city->setActive(false); // Start inactive, enable after loading GTFS data

            $this->em->persist($city);
            ++$seededCount;

            $io->success(sprintf('Seeded city: %s (inactive)', $cityData['name']));
        }

        if ($seededCount > 0) {
            $this->em->flush();
            $io->success(sprintf('Successfully seeded %d cities.', $seededCount));
            $io->note('Cities are created as inactive. Enable them after loading GTFS data.');
        } else {
            $io->info('No new cities to seed.');
        }

        return Command::SUCCESS;
    }
}
