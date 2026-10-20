<?php

namespace App\Repository;

use App\Entity\LeaveRequest;
use App\Entity\User;
use App\Enum\LeaveStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LeaveRequest>
 */
class LeaveRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LeaveRequest::class);
    }

    /**
     * Vrai si la période chevauche, même d'un jour (bornes comprises), une autre demande du même
     * demandeur qui occupe encore sa période : en attente ou acceptée (spec 007, C4).
     */
    public function hasOverlap(User $requester, \DateTimeImmutable $start, \DateTimeImmutable $end): bool
    {
        $blocking = array_map(
            static fn (LeaveStatus $status): string => $status->value,
            array_values(array_filter(LeaveStatus::cases(), static fn (LeaveStatus $status): bool => $status->blocksPeriod())),
        );

        return null !== $this->createQueryBuilder('l')
            ->select('l.id')
            ->where('l.requester = :requester')
            ->andWhere('l.status IN (:blocking)')
            ->andWhere('l.startDate <= :end')
            ->andWhere('l.endDate >= :start')
            ->setParameter('requester', $requester)
            ->setParameter('blocking', $blocking)
            ->setParameter('start', $start, Types::DATE_IMMUTABLE)
            ->setParameter('end', $end, Types::DATE_IMMUTABLE)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Toutes les demandes d'un utilisateur, quel que soit leur état, date de début décroissante
     * (spec 007, C16).
     *
     * @return list<LeaveRequest>
     */
    public function findForRequester(User $requester): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.requester = :requester')
            ->setParameter('requester', $requester)
            ->orderBy('l.startDate', 'DESC')
            ->addOrderBy('l.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Demandes à traiter par un responsable : les demandes en attente des personnes qu'il encadre
     * directement, date de début la plus proche d'abord (spec 007, C6).
     *
     * @return list<LeaveRequest>
     */
    public function findPendingForResponsible(User $responsible): array
    {
        return $this->createQueryBuilder('l')
            ->addSelect('r')
            ->join('l.requester', 'r')
            ->where('r.manager = :responsible')
            ->andWhere('l.status = :status')
            ->setParameter('responsible', $responsible)
            ->setParameter('status', LeaveStatus::Pending->value)
            ->orderBy('l.startDate', 'ASC')
            ->addOrderBy('l.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Congés acceptés de tout le monde, en cours ou à venir : date de fin pas antérieure à $today, date
     * de début croissante (spec 007, C15).
     *
     * @return list<LeaveRequest>
     */
    public function findAcceptedEndingFrom(\DateTimeImmutable $today): array
    {
        return $this->createQueryBuilder('l')
            ->addSelect('r')
            ->join('l.requester', 'r')
            ->where('l.status = :status')
            ->andWhere('l.endDate >= :today')
            ->setParameter('status', LeaveStatus::Accepted->value)
            ->setParameter('today', $today, Types::DATE_IMMUTABLE)
            ->orderBy('l.startDate', 'ASC')
            ->addOrderBy('l.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Demandes en attente de tout le monde, pour l'admin, date de début la plus proche d'abord
     * (spec 007, C18).
     *
     * @return list<LeaveRequest>
     */
    public function findAllPending(): array
    {
        return $this->createQueryBuilder('l')
            ->addSelect('r')
            ->join('l.requester', 'r')
            ->where('l.status = :status')
            ->setParameter('status', LeaveStatus::Pending->value)
            ->orderBy('l.startDate', 'ASC')
            ->addOrderBy('l.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
