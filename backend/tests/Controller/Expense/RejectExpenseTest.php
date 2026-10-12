<?php

namespace App\Tests\Controller\Expense;

use App\Entity\Expense;
use App\Enum\ExpenseStatus;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * POST /api/expenses/{id}/reject, corps { reason } (responsable du propriétaire) — spec 003 (C4, C5, C6, C9).
 */
final class RejectExpenseTest extends ApiTestCase
{
    public function testC4LeManagerRejetteAvecUnMotifEtEnregistreQuiEtQuand(): void
    {
        $team = $this->createTeam();
        $id = $this->submittedExpenseOfSophie($team);
        $this->login($team['marc']);
        $before = time();

        $this->jsonRequest('POST', '/api/expenses/'.$id.'/reject', ['reason' => 'Justificatif manquant']);

        self::assertResponseStatusCodeSame(200);
        $after = time();
        $view = $this->responseJson();
        $this->assertExpenseView($view);
        self::assertSame($id, $view['id']);
        self::assertSame('rejected', $view['status']);
        self::assertSame('Justificatif manquant', $view['rejectionReason']);
        self::assertSame(['id' => $team['marc']->getId(), 'name' => 'Marc Manager'], $view['decidedBy']);
        $this->assertIsoBetween($view['decidedAt'], $before, $after);
        self::assertFalse($view['decidable']);
        // Lue par le manager (pas le propriétaire) : pas modifiable.
        self::assertFalse($view['editable']);

        $stored = $this->findExpense($id);
        self::assertInstanceOf(Expense::class, $stored);
        self::assertSame(ExpenseStatus::Rejected, $stored->getStatus());
        self::assertSame('Justificatif manquant', $stored->getRejectionReason());
        self::assertSame($team['marc']->getId(), $stored->getDecidedBy()?->getId());
        self::assertNotNull($stored->getDecidedAt());
    }

    public function testCasLimiteLeMotifEstConserveSansLesEspacesAutour(): void
    {
        $team = $this->createTeam();
        $id = $this->submittedExpenseOfSophie($team);
        $this->login($team['marc']);

        $this->jsonRequest('POST', '/api/expenses/'.$id.'/reject', ['reason' => "  \u{00A0}Montant erroné\u{3000} \n"]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('Montant erroné', $this->responseJson()['rejectionReason']);
        self::assertSame('Montant erroné', $this->findExpense($id)?->getRejectionReason());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function acceptedReasons(): iterable
    {
        yield '5 caractères exactement' => ['abcde'];
        yield '5 caractères accentués (comptés en caractères, pas en octets)' => ['éèàùç'];
        yield '5 caractères entourés d\'espaces' => ['   abcde   '];
        yield '1000 caractères' => [str_repeat('a', 1000)];
    }

    #[DataProvider('acceptedReasons')]
    public function testC5MotifAccepte(string $reason): void
    {
        $team = $this->createTeam();
        $id = $this->submittedExpenseOfSophie($team);
        $this->login($team['marc']);

        $this->jsonRequest('POST', '/api/expenses/'.$id.'/reject', ['reason' => $reason]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(trim($reason), $this->responseJson()['rejectionReason']);
        $stored = $this->findExpense($id);
        self::assertSame(ExpenseStatus::Rejected, $stored?->getStatus());
        self::assertSame(trim($reason), $stored?->getRejectionReason());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidBodies(): iterable
    {
        yield 'motif absent' => [[]];
        yield 'motif null' => [['reason' => null]];
        yield 'motif vide' => [['reason' => '']];
        yield 'motif fait d\'espaces' => [['reason' => '          ']];
        yield 'motif fait d\'espaces Unicode' => [['reason' => "\u{00A0}\u{00A0}\u{3000}\u{2028}\u{FEFF}\u{00A0}"]];
        yield 'motif de 4 caractères' => [['reason' => 'abcd']];
        yield 'motif de 4 caractères entourés d\'espaces' => [['reason' => '   abcd   ']];
        yield 'motif de 4 caractères entourés d\'espaces insécables' => [['reason' => "\u{00A0}abcd\u{00A0}\u{00A0}"]];
        yield 'motif de 1001 caractères' => [['reason' => str_repeat('a', 1001)]];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('invalidBodies')]
    public function testC5MotifInvalideRenvoie400SurLeChampReasonSansRejeter(array $body): void
    {
        $team = $this->createTeam();
        $id = $this->submittedExpenseOfSophie($team);
        $this->login($team['marc']);

        $this->jsonRequest('POST', '/api/expenses/'.$id.'/reject', $body);

        $this->assertValidationError('reason');
        $this->assertStillSubmitted($id);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notSubmittedStatuses(): iterable
    {
        yield 'validée' => ['approved'];
        yield 'rejetée' => ['rejected'];
    }

    #[DataProvider('notSubmittedStatuses')]
    public function testC6RejeterUneNoteNonSoumiseRenvoie409SansChangement(string $status): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'], status: ExpenseStatus::from($status))->getId();
        $this->login($team['marc']);

        $this->jsonRequest('POST', '/api/expenses/'.$id.'/reject', ['reason' => 'Motif valable']);

        $error = $this->assertErrorResponse(409);
        self::assertArrayNotHasKey('fields', $error);
        $stored = $this->findExpense($id);
        self::assertSame(ExpenseStatus::from($status), $stored?->getStatus());
        self::assertNull($stored?->getRejectionReason());
        self::assertNull($stored?->getDecidedBy());
    }

    public function testC6UnSecondRejetEstRefuseEtLePremierMotifConserve(): void
    {
        $team = $this->createTeam();
        $id = $this->submittedExpenseOfSophie($team);
        $this->login($team['marc']);
        $this->jsonRequest('POST', '/api/expenses/'.$id.'/reject', ['reason' => 'Premier motif']);
        self::assertResponseStatusCodeSame(200);

        $this->jsonRequest('POST', '/api/expenses/'.$id.'/reject', ['reason' => 'Second motif']);

        $this->assertErrorResponse(409);
        $stored = $this->findExpense($id);
        self::assertSame(ExpenseStatus::Rejected, $stored?->getStatus());
        self::assertSame('Premier motif', $stored?->getRejectionReason());
    }

    public function testC6UneValidationApresUnRejetEstRefusee(): void
    {
        $team = $this->createTeam();
        $id = $this->submittedExpenseOfSophie($team);
        $this->login($team['marc']);
        $this->jsonRequest('POST', '/api/expenses/'.$id.'/reject', ['reason' => 'Premier motif']);
        self::assertResponseStatusCodeSame(200);

        $this->client->request('POST', '/api/expenses/'.$id.'/approve');

        $this->assertErrorResponse(409);
        $stored = $this->findExpense($id);
        self::assertSame(ExpenseStatus::Rejected, $stored?->getStatus());
        self::assertSame('Premier motif', $stored?->getRejectionReason());
    }

    public function testAutorisationLeProprietaireNeRejettePasSaPropreNote403(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['marc'], status: ExpenseStatus::Submitted, submittedAt: '2026-10-02T09:00:00+02:00')->getId();
        $this->login($team['marc']);

        $this->jsonRequest('POST', '/api/expenses/'.$id.'/reject', ['reason' => 'Je me rejette']);

        $error = $this->assertErrorResponse(403);
        self::assertArrayNotHasKey('fields', $error);
        $this->assertStillSubmitted($id);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function strangers(): iterable
    {
        yield 'manager d\'une autre équipe' => ['julie'];
        yield 'collègue salarié' => ['tom'];
        yield 'admin, responsable du manager mais pas du salarié' => ['admin'];
    }

    #[DataProvider('strangers')]
    public function testAutorisationToutAutreQueLeResponsableRecoit404(string $reader): void
    {
        $team = $this->createTeam();
        $id = $this->submittedExpenseOfSophie($team);
        $this->login($team[$reader]);

        $this->jsonRequest('POST', '/api/expenses/'.$id.'/reject', ['reason' => 'Motif valable']);

        $error = $this->assertErrorResponse(404);
        self::assertArrayNotHasKey('fields', $error);
        self::assertStringNotContainsString('Note de Sophie', (string) $this->client->getResponse()->getContent());
        $this->assertStillSubmitted($id);
    }

    public function testAutorisationNoteInexistanteRenvoie404(): void
    {
        $team = $this->createTeam();
        $id = $this->submittedExpenseOfSophie($team);
        $this->login($team['marc']);

        $this->jsonRequest('POST', '/api/expenses/'.($id + 1000).'/reject', ['reason' => 'Motif valable']);

        $this->assertErrorResponse(404);
        $this->assertStillSubmitted($id);
    }

    public function testAutorisationSansSessionRenvoie401(): void
    {
        $team = $this->createTeam();
        $id = $this->submittedExpenseOfSophie($team);

        $this->jsonRequest('POST', '/api/expenses/'.$id.'/reject', ['reason' => 'Motif valable']);

        $this->assertErrorResponse(401);
        $this->assertStillSubmitted($id);
    }

    /**
     * @param array<string, \App\Entity\User> $team
     */
    private function submittedExpenseOfSophie(array $team): int
    {
        return (int) $this->createExpense($team['sophie'], 'Note de Sophie', 1250, '2026-10-01', 'repas', ExpenseStatus::Submitted, '2026-10-02T09:00:00+02:00')->getId();
    }

    private function assertStillSubmitted(int $id): void
    {
        $stored = $this->findExpense($id);
        self::assertInstanceOf(Expense::class, $stored);
        self::assertSame(ExpenseStatus::Submitted, $stored->getStatus());
        self::assertNull($stored->getRejectionReason());
        self::assertNull($stored->getDecidedBy());
        self::assertNull($stored->getDecidedAt());
    }

    /**
     * Un brouillon de l'équipe est invisible pour le responsable (404 en lecture, C12) :
     * le décider doit aussi répondre 404, sans révéler son existence par un 409.
     */
    public function testAutorisationDeciderUnBrouillonDeSonEquipeRenvoie404(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'])->getId();
        $this->login($team['marc']);

        $this->jsonRequest('POST', '/api/expenses/'.$id.'/reject', ['reason' => 'Motif valable']);

        $this->assertErrorResponse(404);
        self::assertSame(ExpenseStatus::Draft, $this->findExpense($id)?->getStatus());
    }

    public function testRelectureLeMotifSeCompteEnCaracteresEtSansBlancsUnicodeAutour(): void
    {
        $team = $this->createTeam();
        $first = (int) $this->createExpense($team['sophie'], status: ExpenseStatus::Submitted)->getId();
        $second = (int) $this->createExpense($team['sophie'], status: ExpenseStatus::Submitted)->getId();
        $this->login($team['marc']);

        // 5 emoji = 5 caractères : accepté, comme côté front
        $this->jsonRequest('POST', '/api/expenses/'.$first.'/reject', ['reason' => str_repeat('🚕', 5)]);
        self::assertResponseStatusCodeSame(200);

        // U+0085 (fin de ligne) retiré comme les autres blancs : reste 4 caractères, refusé
        $this->jsonRequest('POST', '/api/expenses/'.$second.'/reject', ['reason' => "\u{0085}abcd"]);
        $this->assertValidationError('reason');
    }
}
