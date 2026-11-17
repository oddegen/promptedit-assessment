import type {
    Generation,
    GenerationRequest,
    GenerationStatus,
    Quality,
    Size,
} from '@/types';

export const shapes: {
    size: Size;
    name: string;
    width: number;
    height: number;
}[] = [
    { size: '1024x1024', name: 'Square', width: 1024, height: 1024 },
    { size: '1536x1024', name: 'Wide', width: 1536, height: 1024 },
    { size: '1024x1536', name: 'Tall', width: 1024, height: 1536 },
];

export const qualities: { quality: Quality; name: string; hint: string }[] = [
    { quality: 'low', name: 'Low', hint: 'Quick drafts' },
    { quality: 'medium', name: 'Medium', hint: 'Good for most images' },
    { quality: 'high', name: 'High', hint: 'Finest detail, slowest' },
];

export function shapeOf(size: Size) {
    return shapes.find((shape) => shape.size === size) ?? shapes[0];
}

export function qualityOf(quality: Quality) {
    return qualities.find((tier) => tier.quality === quality) ?? qualities[1];
}

export function aspectRatio(size: Size): number {
    const { width, height } = shapeOf(size);

    return width / height;
}

export function isInFlight(status: GenerationStatus): boolean {
    return ['pending', 'queued', 'processing'].includes(status);
}

export function secondsWaiting(generation: Generation, now: Date): number {
    const since = generation.started_at ?? generation.created_at;

    return Math.max(
        0,
        Math.floor((now.getTime() - new Date(since).getTime()) / 1000),
    );
}

export function waitingNote(
    generation: Generation,
    seconds: number,
): string | null {
    if (seconds < 30) {
        return null;
    }

    if (generation.status === 'processing') {
        return seconds < 120
            ? 'Taking a little longer than usual'
            : 'Still working on it';
    }

    return "Busy right now. We'll keep trying";
}

export function hasExpired(generation: Generation, now = new Date()): boolean {
    return (
        generation.image_url_expires_at !== null &&
        new Date(generation.image_url_expires_at) <= now
    );
}

type Failure = {
    label: string;
    message: string;
    action: 'edit' | 'retry' | null;
};

const failures: Record<string, Failure> = {
    content_policy_violation: {
        label: 'Blocked',
        message: 'This one goes against our content rules. Try rewording it.',
        action: 'edit',
    },
    validation_failed: {
        label: "Couldn't start",
        message: "We couldn't use this one. Check your reference images.",
        action: 'edit',
    },
    daily_limit_exceeded: {
        label: 'Paused',
        message: "We're at capacity for today. Try again tomorrow.",
        action: null,
    },
    generation_failed: {
        label: 'Failed',
        message: "This one didn't come out. Give it another go.",
        action: 'retry',
    },
};

export function failureOf(code: string | null): Failure {
    return (
        failures[code ?? ''] ?? {
            label: 'Failed',
            message: 'Something went wrong on our end.',
            action: 'retry',
        }
    );
}

export function requestFrom(generation: Generation): GenerationRequest {
    return {
        prompt: generation.prompt,
        size: generation.size,
        quality: generation.quality,
        reference_images: [...generation.reference_images],
        parent_id: generation.id,
    };
}
