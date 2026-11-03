<?php

namespace App\Http\Concerns;

trait PaginatesArray
{
    /**
     * Paginate a pre-fetched array of records.
     * Page mode:    $page set → slice + metadata.
     * Entries mode: $entries set, $page null → first N records, no metadata.
     */
    protected function paginateArray(
        array $records,
        ?int $page,
        ?int $entries = null,
        int $perPage = 50
    ): array {
        if (! empty($entries) && empty($page)) {
            $data = array_slice($records, 0, $entries);

            return [
                'pagination' => null,
                'entries' => count($data),
                'data' => $data,
            ];
        }

        $total = count($records);
        $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $offset = (($page ?? 1) - 1) * $perPage;

        return [
            'pagination' => [
                'current_page' => $page ?? 1,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => min($offset + $perPage, $total),
                'has_more_pages' => ($page ?? 1) < $lastPage,
            ],
            'entries' => $perPage,
            'data' => array_slice($records, $offset, $perPage),
        ];
    }
}
