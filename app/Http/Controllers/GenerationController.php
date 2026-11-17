<?php

namespace App\Http\Controllers;

use App\Actions\CreateGeneration;
use App\Enums\GenerationStatus;
use App\Http\Requests\Generations\StoreGenerationRequest;
use App\Http\Resources\GenerationResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GenerationController extends Controller
{
    public function index(Request $request, #[CurrentUser] User $user): Response
    {
        $filter = $request->string('filter')->toString();

        $statuses = match ($filter) {
            'running' => [GenerationStatus::Pending, GenerationStatus::Queued, GenerationStatus::Processing],
            'failed' => [GenerationStatus::Failed],
            default => null,
        };

        return Inertia::render('generations/Index', [
            'generations' => Inertia::scroll(fn () => GenerationResource::collection(
                $user->generations()
                    ->withVersionCount()
                    ->withVersionNumber()
                    ->when($statuses, fn ($query, array $statuses) => $query->whereIn('status', $statuses))
                    ->latest()
                    ->orderByDesc('id')
                    ->paginate(30),
            )),
            'filter' => $statuses === null ? 'all' : $filter,
        ]);
    }

    public function store(StoreGenerationRequest $request, #[CurrentUser] User $user, CreateGeneration $create): RedirectResponse
    {
        $create->handle(
            $user,
            $request->prompt(),
            $request->size(),
            $request->quality(),
            $request->referenceImages(),
            $request->versionOf(),
        );

        return back();
    }

    public function show(#[CurrentUser] User $user, string $generation): Response
    {
        $generation = $user->generations()->findOrFail($generation);
        $original = $generation->parent_id ?? $generation->id;
        $versions = $user->generations()
            ->where(fn ($query) => $query->whereKey($original)->orWhere('parent_id', $original))
            ->oldest()
            ->orderBy('id')
            ->get();

        return Inertia::render('generations/Show', [
            'generation' => new GenerationResource($generation),
            'versions' => GenerationResource::collection($versions),
            'spent' => (int) $versions->sum('price'),
            'receipt' => $generation->price === null || $generation->cost === null ? null : [
                'input_text_tokens' => $generation->input_text_tokens,
                'input_image_tokens' => $generation->input_image_tokens,
                'output_tokens' => $generation->output_tokens,
                'reasoning_tokens' => $generation->reasoning_tokens,
                'cost' => $generation->cost,
                'fee' => $generation->price - $generation->cost,
            ],
        ]);
    }
}
