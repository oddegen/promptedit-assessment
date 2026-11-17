<?php

namespace App\Http\Controllers;

use App\Http\Resources\GenerationResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudioController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, #[CurrentUser] User $user): Response
    {
        return Inertia::render('Studio', [
            'generations' => GenerationResource::collection(
                $user->generations()->latestVersions()->withVersionCount()->latest()->orderByDesc('id')->limit(30)->get(),
            ),
            'summary' => [
                'count' => $user->generations()->latestVersions()->count(),
                'spent' => (int) $user->generations()->sum('price'),
            ],
            'prefill' => $request->session()->pull('draft'),
        ]);
    }
}
