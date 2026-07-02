<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Base controller. Pulls in {@see AuthorizesRequests} so controllers can call
 * `$this->authorize()` against the permission gates registered in
 * {@see \App\Providers\AuthServiceProvider}.
 */
abstract class Controller
{
    use AuthorizesRequests;
}
