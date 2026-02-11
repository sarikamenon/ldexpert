<?php

use App\Domain\School\Repositories\SchoolCalendarEventRepositoryInterface;
use App\Domain\School\Services\SchoolCalendarService;
use Carbon\Carbon;
use Mockery\MockInterface;

beforeEach(function () {
    /** @var MockInterface $repository */
    $repository = \Mockery::mock(SchoolCalendarEventRepositoryInterface::class);
    $this->repository = $repository;
    $this->service = new SchoolCalendarService($this->repository);
});

afterEach(function () {
    \Mockery::close();
});

test('school calendar service checks holidays via repository', function () {
    $date = Carbon::parse('2026-01-05');

    $this->repository
        ->shouldReceive('hasHolidayOnDate')
        ->once()
        ->with(12, $date)
        ->andReturn(true);

    expect($this->service->isHolidayDate(12, $date))->toBeTrue();
});
