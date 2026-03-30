<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\QGlobRequest;

use App\Domain\QGlobRequest\Repositories\QGlobRequestRepositoryInterface;
use App\Domain\QGlobRequest\Services\QGlobRequestService;
use App\DTOs\CreateQGlobRequestDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\QGlobRequestFilterDTO;
use App\Models\QGlobRequest;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

final class QGlobRequestServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_create_delegates_to_repository(): void
    {
        $dto = new CreateQGlobRequestDTO(1, 2, '2026-05-01', '10:00', null);
        $model = Mockery::mock(QGlobRequest::class);

        $repo = Mockery::mock(QGlobRequestRepositoryInterface::class);
        $repo->expects('create')->once()->with($dto)->andReturn($model);

        $service = new QGlobRequestService($repo);
        self::assertSame($model, $service->create($dto));
    }

    public function test_list_for_data_tables_passes_therapist_scope(): void
    {
        $filters = new QGlobRequestFilterDTO(null, null, null, null);
        $params = new DataTablesParamsDTO(1, 0, 10, null, null, 'asc');
        $rows = new EloquentCollection();

        $repo = Mockery::mock(QGlobRequestRepositoryInterface::class);
        $repo->expects('listForDataTables')
            ->once()
            ->with($filters, $params, 7)
            ->andReturn([
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'rows' => $rows,
            ]);

        $service = new QGlobRequestService($repo);
        $result = $service->listForDataTables($filters, $params, 7);

        self::assertSame(0, $result['recordsTotal']);
        self::assertSame($rows, $result['rows']);
    }

    public function test_student_eligibility_delegates(): void
    {
        $repo = Mockery::mock(QGlobRequestRepositoryInterface::class);
        $repo->expects('isStudentEligibleForTherapist')->once()->with(4, 8)->andReturn(true);

        $service = new QGlobRequestService($repo);
        self::assertTrue($service->studentIsEligibleForTherapist(4, 8));
    }
}
