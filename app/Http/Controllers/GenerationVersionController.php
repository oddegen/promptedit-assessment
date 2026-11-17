<?php

namespace App\Http\Controllers;

use App\Actions\CreateGeneration;
use App\Http\Requests\Generations\StoreGenerationRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class GenerationVersionController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(StoreGenerationRequest $request, #[CurrentUser] User $user, CreateGeneration $create, string $generation): RedirectResponse
    {
        $original = $user->generations()->findOrFail($generation);

        $version = $create->handle(
            $user,
            $request->prompt(),
            $request->size(),
            $request->quality(),
            $request->referenceImages(),
            $original,
        );

        return to_route('generations.show', $version);
    }
}
