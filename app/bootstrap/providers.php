<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\PaymentServiceProvider::class,
    ...(app()->environment('local') ? [App\Providers\TelescopeServiceProvider::class] : []),
];
