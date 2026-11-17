<?php

namespace App\Http\Controllers;

use App\Http\Requests\Generations\DraftGenerationRequest;
use Illuminate\Http\RedirectResponse;

class DraftController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(DraftGenerationRequest $request): RedirectResponse
    {
        $request->session()->put('draft', $request->draft());

        return $request->user() ? to_route('studio') : back();
    }
}
