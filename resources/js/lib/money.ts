type PriceStyle = 'standard' | 'ceiling' | 'floor' | 'exact';

type PriceFormat = Omit<Intl.NumberFormatOptions, 'notation'>;

const formats: Record<PriceStyle, PriceFormat> = {
    standard: {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
        minimumSignificantDigits: 2,
        maximumSignificantDigits: 2,
        roundingPriority: 'morePrecision',
    },
    ceiling: {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
        minimumSignificantDigits: 2,
        maximumSignificantDigits: 2,
        roundingPriority: 'morePrecision',
        roundingMode: 'ceil',
    },
    floor: {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
        minimumSignificantDigits: 2,
        maximumSignificantDigits: 2,
        roundingPriority: 'morePrecision',
        roundingMode: 'floor',
    },
    exact: {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
        maximumFractionDigits: 9,
    },
};

export function dollars(nanodollars: number): `${number}` {
    return `${nanodollars}E-9` as `${number}`;
}

export function priceFormat(style: PriceStyle = 'standard'): PriceFormat {
    return formats[style];
}

export function formatPrice(
    nanodollars: number,
    style: PriceStyle = 'standard',
): string {
    return new Intl.NumberFormat('en-US', formats[style]).format(
        dollars(nanodollars),
    );
}

export function formatPriceRange(minimum: number, maximum: number): string {
    const low = formatPrice(minimum);
    const high = formatPrice(maximum, 'ceiling');

    return low === high ? high : `${low}–${high}`;
}
