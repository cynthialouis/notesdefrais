<?php

namespace App\Stats;

use App\Dto\CategoryTotalView;
use App\Dto\ExpenseCategoryRef;
use App\Dto\MonthTotalView;
use App\Dto\MyStatsView;
use App\Dto\StatusTotalView;
use App\Dto\TeamStatsView;
use App\Entity\Expense;
use App\Entity\User;
use App\Enum\ExpenseStatus;
use App\Repository\CategoryRepository;
use App\Repository\ExpenseRepository;
use Psr\Clock\ClockInterface;

/**
 * Seul endroit des totaux du tableau de bord (spec 006, C1 à C5, C8) : calculés ici en centimes
 * entiers, le front les affiche sans les recalculer. États, mois et catégories sont toujours tous
 * présents, à 0 si besoin.
 */
final class DashboardStats
{
    public function __construct(
        private readonly ExpenseRepository $expenses,
        private readonly CategoryRepository $categories,
        private readonly ClockInterface $clock,
    ) {
    }

    public function period(): StatsPeriod
    {
        return StatsPeriod::endingAt($this->clock->now());
    }

    public function forOwner(User $owner): MyStatsView
    {
        $totals = $this->expenses->totalsByStatusForOwner($owner);

        return new MyStatsView(array_map(
            static fn (ExpenseStatus $status): StatusTotalView => new StatusTotalView(
                $status,
                $totals[$status->value]['count'] ?? 0,
                $totals[$status->value]['totalCents'] ?? 0,
            ),
            ExpenseStatus::cases(),
        ));
    }

    public function forTeam(User $responsible): TeamStatsView
    {
        $period = $this->period();
        $approved = $this->teamExpenses($responsible, $period);

        $byMonth = array_fill_keys($period->months(), ['count' => 0, 'totalCents' => 0]);
        $byCategory = [];
        foreach ($approved as $expense) {
            $month = $expense->getDate()->format('Y-m');
            ++$byMonth[$month]['count'];
            $byMonth[$month]['totalCents'] += $expense->getAmountCents();
            $slug = $expense->getCategory()->getSlug();
            $byCategory[$slug]['count'] = ($byCategory[$slug]['count'] ?? 0) + 1;
            $byCategory[$slug]['totalCents'] = ($byCategory[$slug]['totalCents'] ?? 0) + $expense->getAmountCents();
        }

        $categoryTotals = [];
        foreach ($this->categories->findAllOrdered() as $category) {
            $categoryTotals[] = new CategoryTotalView(
                ExpenseCategoryRef::fromEntity($category),
                $byCategory[$category->getSlug()]['count'] ?? 0,
                $byCategory[$category->getSlug()]['totalCents'] ?? 0,
            );
        }
        $monthTotals = [];
        foreach ($byMonth as $month => $total) {
            $monthTotals[] = new MonthTotalView((string) $month, $total['count'], $total['totalCents']);
        }

        return new TeamStatsView(
            $period->from->format('Y-m-d'),
            $period->to->format('Y-m-d'),
            \count($approved),
            array_sum(array_map(static fn (Expense $expense): int => $expense->getAmountCents(), $approved)),
            $categoryTotals,
            $monthTotals,
        );
    }

    /**
     * Notes validées de l'équipe directe sur la période, pour les totaux et l'export (C6, C8).
     *
     * @return list<Expense>
     */
    public function teamExpenses(User $responsible, ?StatsPeriod $period = null): array
    {
        $period ??= $this->period();

        return $this->expenses->findApprovedForResponsible($responsible, $period->from, $period->to);
    }
}
