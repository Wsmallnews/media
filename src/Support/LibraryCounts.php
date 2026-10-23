<?php

namespace Wsmallnews\Media\Support;

/**
 * Per-request cache for library counts (per type and uncategorized), shared by every picker
 * in a form so the same aggregate query runs once.
 *
 * Bound as scoped rather than static: static state leaks across requests and tests.
 */
class LibraryCounts
{
    /** @var array<string, array<string, int>> Keyed by tenant id */
    public array $typeCounts = [];

    /** @var array<string, int> Keyed by tenant id */
    public array $uncategorized = [];
}
