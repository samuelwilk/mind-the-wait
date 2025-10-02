<?php

namespace App\Repository;

use App\Entity\Route;
use App\Enum\RouteTypeEnum;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<Route>
 */
class RouteRepository extends BaseRepository
{
    public function __construct(EntityManagerInterface $em, ManagerRegistry $registry)
    {
        parent::__construct($em, $registry, Route::class);
    }

    public function findOneByGtfsId(string $gtfsId): ?Route
    {
        return $this->findOneBy(['gtfsId' => $gtfsId]);
    }

    /** @return array<string, Route> keyed by gtfsId */
    public function mapByGtfsId(): array
    {
        $all = $this->createQueryBuilder('r')->getQuery()->getResult();
        $out = [];
        foreach ($all as $r) {
            $out[$r->getGtfsId()] = $r;
        }

        return $out;
    }

    public function upsert(string $gtfsId, ?string $shortName, ?string $longName, ?string $colour, ?RouteTypeEnum $type): Route
    {
        $route = $this->findOneByGtfsId($gtfsId) ?? new Route();

        $route->setGtfsId($gtfsId);
        $route->setShortName($shortName ?: null);
        $route->setLongName($longName ?: null);
        $route->setColour($colour ?: null);
        $route->setRouteType($type);
        $this->save($route, false);

        return $route;
    }
}
