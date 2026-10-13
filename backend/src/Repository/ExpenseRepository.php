<?php

namespace App\Repository;

use App\Entity\Expense;
use App\Entity\User;
use App\Enum\ExpenseStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
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

    /**
     * Notes à traiter par un responsable : les notes soumises des personnes qu'il encadre directement,
     * les plus anciennes soumissions d'abord (spec 003, C1).
     *
     * @return list<Expense>
     */
    public function findSubmittedForResponsible(User $responsible): array
    {
        return $this->createQueryBuilder('e')
            ->addSelect('c', 'o')
            ->join('e.category', 'c')
            ->join('e.owner', 'o')
            ->where('o.manager = :responsible')
            ->andWhere('e.status = :status')
            ->setParameter('responsible', $responsible)
            ->setParameter('status', ExpenseStatus::Submitted)
            ->orderBy('e.submittedAt', 'ASC')
            ->addOrderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Nombre et total de ses notes par état, toutes dates confondues (spec 006, C1) ; les états sans note
     * sont absents du résultat.
     *
     * @return array<string, array{count: int, totalCents: int}> indexé par la valeur de l'état
     */
    public function totalsByStatusForOwner(User $owner): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('e.status AS status', 'COUNT(e.id) AS notes', 'SUM(e.amountCents) AS total')
            ->where('e.owner = :owner')
            ->setParameter('owner', $owner)
            ->groupBy('e.status')
            ->getQuery()
            ->getArrayResult();

        $totals = [];
        foreach ($rows as $row) {
            $status = $row['status'] instanceof ExpenseStatus ? $row['status']->value : (string) $row['status'];
            $totals[$status] = ['count' => (int) $row['notes'], 'totalCents' => (int) $row['total']];
        }

        return $totals;
    }

    /**
     * Notes validées des personnes qu'un responsable encadre directement, datées dans la période
     * (spec 006, C3, C4, C6, C8), par date de dépense puis ordre de création.
     *
     * @return list<Expense>
     */
    public function findApprovedForResponsible(User $responsible, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('e')
            ->addSelect('c', 'o', 'd')
            ->join('e.category', 'c')
            ->join('e.owner', 'o')
            ->leftJoin('e.decidedBy', 'd')
            ->where('o.manager = :responsible')
            ->andWhere('e.status = :status')
            ->andWhere('e.date BETWEEN :from AND :to')
            ->setParameter('responsible', $responsible)
            ->setParameter('status', ExpenseStatus::Approved)
            ->setParameter('from', $from, Types::DATE_IMMUTABLE)
            ->setParameter('to', $to, Types::DATE_IMMUTABLE)
            ->orderBy('e.date', 'ASC')
            ->addOrderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
