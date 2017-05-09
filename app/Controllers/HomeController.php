<?php

namespace Application\Controllers;

use Application\Contracts\RouterSetupContract;
use Illuminate\View\View;
use Route;

/**
 * Class HomeController
 */
class HomeController extends Controller implements RouterSetupContract
{
    /**
     * @inheritdoc
     */
    static public function routerSetup(): void
    {
        Route::get('/', 'HomeController@routeHome');
    }

    /**
     * Return the home page.
     */
    public function routeHome(): View
    {
        return view('home.home', [
            'scoreEntanglement' => ProcessController::POINTS_ENTANGLEMENT,
            'scoreRecentivity'  => ProcessController::POINTS_RECENTIVITY,
        ]);
    }
}
