<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class StyleguideController extends Controller
{
    /**
     * Affiche le guide de style avec tous les composants et leurs variantes.
     */
    public function __invoke(): View
    {
        return view('dev.styleguide');
    }
}
