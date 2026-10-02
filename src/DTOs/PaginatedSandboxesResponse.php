<?php

namespace ElliottLawson\Daytona\DTOs;

use ElliottLawson\Daytona\Sandbox;

/**
 * Response returned by Daytona's paginated sandbox listing.
 *
 * Daytona 0.180+ replaced the plain array response for GET /sandbox with a
 * cursor-paginated object: `{ "items": [...], "nextCursor": string|null }`.
 * This DTO mirrors that shape while exposing the items as {@see Sandbox}
 * instances so callers can keep working with the behavioral wrapper.
 */
class PaginatedSandboxesResponse
{
    /**
     * @param  Sandbox[]  $items  Sandboxes in the current page
     * @param  string|null  $nextCursor  Cursor to fetch the next page, or null when exhausted
     */
    public function __construct(
        public readonly array $items,
        public readonly ?string $nextCursor = null,
    ) {}

    /**
     * Build the response from an already-mapped list of sandboxes.
     *
     * @param  Sandbox[]  $items
     */
    public static function make(array $items, ?string $nextCursor = null): self
    {
        return new self($items, $nextCursor);
    }

    /**
     * Whether another page of results is available.
     */
    public function hasMore(): bool
    {
        return $this->nextCursor !== null && $this->nextCursor !== '';
    }

    /**
     * The number of sandboxes in the current page.
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Whether the current page contains no sandboxes.
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * @return array{items: array, nextCursor: string|null}
     */
    public function toArray(): array
    {
        return [
            'items' => $this->items,
            'nextCursor' => $this->nextCursor,
        ];
    }
}
