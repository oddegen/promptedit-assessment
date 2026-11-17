export type ReportRange = '7' | '30' | 'month';

export type ReportSummary = {
    generations: number;
    cost: number;
    charged: number;
    profit: number;
    margin: string | null;
};

export type ReportDay = {
    date: string;
    generations: number;
    cost: number;
    charged: number;
};

export type ReportUser = {
    id: number;
    name: string;
    email: string;
    generations: number;
    cost: number;
    charged: number;
};

export type ProviderDay = {
    cost: number;
    generations: number;
    difference: number;
    status: 'matched' | 'differs' | 'in_progress';
};

export type ProviderUsage = {
    available: boolean;
    days: Record<string, ProviderDay>;
};
