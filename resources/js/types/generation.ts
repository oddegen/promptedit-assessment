export type GenerationStatus =
    | 'pending'
    | 'queued'
    | 'processing'
    | 'succeeded'
    | 'failed';

export type Size = '1024x1024' | '1536x1024' | '1024x1536';

export type Quality = 'low' | 'medium' | 'high';

export type Generation = {
    id: string;
    status: GenerationStatus;
    prompt: string;
    size: Size;
    quality: Quality;
    reference_images: string[];
    min_price: number;
    estimated_price: number;
    max_price: number;
    price: number | null;
    error_code: string | null;
    image_url: string | null;
    image_url_expires_at: string | null;
    created_at: string;
    started_at: string | null;
    completed_at: string | null;
    version_count?: number;
};

export type GenerationRequest = {
    prompt: string;
    size: Size;
    quality: Quality;
    reference_images: string[];
    parent_id: string | null;
};

export type TokenCounts = {
    input_text_tokens: number;
    input_image_tokens: number;
    output_tokens: number;
    reasoning_tokens: number;
};

export type Estimate = {
    minimum: number;
    expected: number;
    maximum: number;
    tiers: Record<Quality, number>;
    tokens: {
        minimum: TokenCounts;
        expected: TokenCounts;
        maximum: TokenCounts;
    };
    fee_percent: number;
};

export type Receipt = TokenCounts & {
    cost: number;
    fee: number;
};

export type GenerationSummary = {
    count: number;
    spent: number;
};
