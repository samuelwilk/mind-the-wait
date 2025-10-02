<?php

namespace App\Repository;

use App\Repository\Exception\DuplicateEntityException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

use function count;
use function sprintf;

/**
 * @template T of object
 *
 * @extends ServiceEntityRepository<T>
 */
abstract class BaseRepository extends ServiceEntityRepository
{
    private readonly string $entityClass;

    public function __construct(private EntityManagerInterface $em, ManagerRegistry $registry, string $entityClass)
    {
        parent::__construct($registry, $entityClass);
        $this->entityClass = $entityClass;
    }

    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    /** @param T $entity */
    public function save(object $entity, bool $flush = true): void
    {
        $this->em->persist($entity);
        $this->flushIf($flush);
    }

    /** @param T $entity */
    public function remove(object $entity, bool $flush = true): void
    {
        $this->em->remove($entity);
        $this->flushIf($flush);
    }

    /** @param iterable<T> $entities */
    public function saveAll(iterable $entities, bool $flush = true): void
    {
        foreach ($entities as $e) {
            $this->em->persist($e);
        }
        $this->flushIf($flush);
    }

    public function flush(): void
    {
        $this->em->flush();
    }

    /** @param T $entity */
    public function refresh(object $entity): void
    {
        $this->em->refresh($entity);
    }

    /** @return T */
    public function findOrFail(int|string $id): object
    {
        $entity = $this->find($id);
        if (!$entity) {
            throw new EntityNotFoundException(sprintf('%s(%s) not found', $this->getClassName(), (string) $id));
        }

        return $entity;
    }

    /**
     * Wrap a unit of work in a transaction.
     *
     * @template R
     *
     * @param callable():R $fn
     *
     * @return R
     */
    public function transactional(callable $fn): mixed
    {
        // Doctrine >= 2.12
        return $this->em->wrapInTransaction($fn);
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $qb
     *
     * @return array{items: list<object>, total: int}
     */
    public function paginate($qb, int $page = 1, int $perPage = 25): array
    {
        $first = max(0, ($page - 1) * $perPage);
        $qb->setFirstResult($first)->setMaxResults($perPage);

        $paginator = new Paginator($qb, true);
        $items     = iterator_to_array($paginator);
        $total     = count($paginator);

        return ['items' => $items, 'total' => $total];
    }

    /** Map DB unique constraint errors to a domain-level exception */
    protected function flushIf(bool $flush): void
    {
        if (!$flush) {
            return;
        }
        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException $e) {
            throw new DuplicateEntityException($e->getMessage(), previous: $e);
        }
    }

    public function getClassName(): string
    {
        return $this->getClassMetadata()->getName();
    }
}
