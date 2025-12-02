<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Domain\Time\UserTimezoneService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Tests\TestCase;

final class CollectionWithUserTimezoneMacroTest extends TestCase
{

    public function test_macro_converts_created_at_and_updated_at_to_local_attributes(): void
    {
        $user = new User();
        $user->timezone = 'America/New_York';

        $model = new class extends Model {
            protected $guarded = [];
        };

        $model->setAttribute('created_at', Carbon::create(2025, 1, 15, 15, 0, 0, 'UTC'));
        $model->setAttribute('updated_at', Carbon::create(2025, 1, 15, 16, 0, 0, 'UTC'));

        $collection = Collection::make([$model])->withUserTimezone($user);

        /** @var Model $item */
        $item = $collection->first();

        $this->assertTrue($item->getAttribute('created_at_local') instanceof Carbon);
        $this->assertTrue($item->getAttribute('updated_at_local') instanceof Carbon);

        $this->assertSame('2025-01-15 10:00:00', $item->getAttribute('created_at_local')->format('Y-m-d H:i:s'));
        $this->assertSame('2025-01-15 11:00:00', $item->getAttribute('updated_at_local')->format('Y-m-d H:i:s'));
    }

    public function test_macro_leaves_non_models_untouched(): void
    {
        $data = ['foo', 'bar'];

        $collection = Collection::make($data)->withUserTimezone(null);

        $this->assertSame($data, $collection->all());
    }
}
