<?php

namespace App\Repository;

use App\Entity\Expense;
use App\Entity\ExpenseEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExpenseEvent>
 */
class ExpenseEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExpenseEvent::class);
    }

    /**
     * Historique d'une note, du plus ancien au plus récent.
     *
     * @return list<ExpenseEvent>
     */
    public function findForExpense(Expense $expense): array
    {
        return $this->createQueryBuilder('e')
            ->addSelect('a')
            ->join('e.actor', 'a')
            ->where('e.expense = :expense')
            ->setParameter('expense', $expense)
            ->orderBy('e.createdAt', 'ASC')
            ->addOrderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
