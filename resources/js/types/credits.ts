export type Credits = {
    balance: number;
    held: number;
    available: number;
    running: number;
};

export type CreditPack = {
    dollars: number;
    amount: number;
    images: number | null;
};

export type CreditTransaction = {
    id: number;
    type: 'bonus' | 'purchase' | 'charge';
    description: string;
    generation_id: string | null;
    amount: number;
    balance_after: number;
    created_at: string;
};

export type HeldGeneration = {
    id: string;
    prompt: string;
    max_price: number;
    created_at: string;
};
