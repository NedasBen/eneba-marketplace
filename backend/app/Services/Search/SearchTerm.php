<?php

namespace App\Services\Search;

/**
 * A search query after normalization and typo correction.
 */
final readonly class SearchTerm
{
    /** Below this many letters/digits there are too few trigrams for fuzzy matching. */
    public const FUZZY_MIN_LENGTH = 4;

    public function __construct(
        public string $original,
        public string $normalized,
        public string $corrected,
    ) {}

    /**
     * @return list<string>
     */
    public function tokens(): array
    {
        return explode(' ', $this->corrected);
    }

    public function allowsFuzzyMatching(): bool
    {
        return strlen(str_replace(' ', '', $this->corrected)) >= self::FUZZY_MIN_LENGTH;
    }

    public function wasCorrected(): bool
    {
        return $this->corrected !== $this->normalized;
    }

    /**
     * What the API reports, so the UI can say "Showing results for witcher".
     *
     * @return array{original: string, corrected: string|null}
     */
    public function toArray(): array
    {
        return [
            'original' => $this->original,
            'corrected' => $this->wasCorrected() ? $this->corrected : null,
        ];
    }
}
