<?php

namespace App\Repository;

use App\Entity\Expense;
use App\Entity\User;
use App\Enum\ExpenseStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Expense>
 */
class ExpenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Expense::class);
    }

    /**
     * Notes d'un utilisateur, les plus récentes d'abord (date de dépense, puis ordre de création).
     *
     * @return list<Expense>
     */
    public function findForOwner(User $owner, ?ExpenseStatus $status = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->addSelect('c')
            ->join('e.category', 'c')
            ->where('e.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('e.date', 'DESC')
            ->addOrderBy('e.id', 'DESC');

        if (null !== $status) {
            $qb->andWhere('e.status = :status')->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }
}
