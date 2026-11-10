<?php

namespace App\Providers;

use App\Models\RentalBroker;
use App\Models\RentalCustomer;
use App\Models\ResaleBroker;
use App\Models\ResaleCustomer;
use App\Support\DateTimeLocale;
use App\Support\DateTimePresenter;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(
            DateTimeLocale::class,
            static fn ($app): DateTimeLocale => DateTimeLocale::fromAcceptLanguage(
                $app['request']->header('Accept-Language'),
            ),
        );

        $this->app->scoped(
            DateTimePresenter::class,
            static fn ($app): DateTimePresenter => new DateTimePresenter($app->make(DateTimeLocale::class)),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DatePicker::configureUsing(
            static function (DatePicker $picker): DatePicker {
                $presenter = app(DateTimePresenter::class);
                $locale = app(DateTimeLocale::class);

                return $picker
                    ->native(false)
                    ->locale($locale->getPickerLocale())
                    ->displayFormat($presenter->getFilamentDatePattern())
                    ->timezone('Africa/Cairo');
            },
        );

        DateTimePicker::configureUsing(
            static function (DateTimePicker $picker): DateTimePicker {
                $presenter = app(DateTimePresenter::class);
                $locale = app(DateTimeLocale::class);

                return $picker
                    ->native(false)
                    ->locale($locale->getPickerLocale())
                    ->displayFormat($presenter->getFilamentDateTimePattern())
                    ->timezone('Africa/Cairo');
            },
        );

        Relation::morphMap([
            'rental_customer' => RentalCustomer::class,
            'resale_customer' => ResaleCustomer::class,
            'rental_broker' => RentalBroker::class,
            'resale_broker' => ResaleBroker::class,
        ]);
    }
}
