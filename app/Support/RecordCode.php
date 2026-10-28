<?php

namespace App\Support;

use App\Enums\Department;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class RecordCode
{
    public const TYPE_REQUEST = 'request';
    public const TYPE_CUSTOMER = 'customer';

    public static function prefix(Department $department, string $type): string
    {
        return match ([$department, $type]) {
            [Department::Rental, self::TYPE_REQUEST] => 'RN-',
            [Department::Resale, self::TYPE_REQUEST] => 'RS-',
            [Department::Rental, self::TYPE_CUSTOMER] => 'RC-',
            [Department::Resale, self::TYPE_CUSTOMER] => 'SC-',
            default => throw new InvalidArgumentException("Invalid department or record type: {$department->value}, {$type}"),
        };
    }

    /**
     * @return array<string>
     */
    public static function allPrefixes(): array
    {
        return ['RN-', 'RS-', 'RC-', 'SC-'];
    }

    /**
     * @return array<string>
     */
    public static function otherPrefixes(Department $department, string $type): array
    {
        $current = self::prefix($department, $type);

        return array_values(array_filter(
            self::allPrefixes(),
            fn (string $p): bool => $p !== $current,
        ));
    }

    public static function generate(Department $department, string $type, int|string|null $id): ?string
    {
        if (blank($id) || (int) $id <= 0) {
            return null;
        }

        return sprintf('%s%02d', self::prefix($department, $type), (int) $id);
    }

    public static function request(Department $department, int|string|null $id): ?string
    {
        return self::generate($department, self::TYPE_REQUEST, $id);
    }

    public static function customer(Department $department, int|string|null $id): ?string
    {
        return self::generate($department, self::TYPE_CUSTOMER, $id);
    }

    /**
     * Parses a search query into an ID for the given department and type.
     *
     * Returns:
     * - positive int if search query matches the expected prefix (e.g. RN-01) or is pure digits.
     * - false if search query has an explicit alien prefix (e.g. RS-000012 in Rental).
     * - null if search query does not look like a code or ID (e.g. names/words).
     */
    public static function parseId(string $query, Department $department, string $type): int|false|null
    {
        $query = trim($query);
        if ($query === '') {
            return null;
        }

        $upper = strtoupper($query);

        foreach (self::otherPrefixes($department, $type) as $alien) {
            if (str_starts_with($upper, $alien)) {
                return false;
            }
        }

        $expectedPrefix = self::prefix($department, $type);
        if (str_starts_with($upper, $expectedPrefix)) {
            $numPart = substr($upper, strlen($expectedPrefix));
            if (ctype_digit($numPart) && (int) $numPart > 0) {
                return (int) $numPart;
            }

            return false;
        }

        if (ctype_digit($query) && (int) $query > 0) {
            return (int) $query;
        }

        return null;
    }

    /**
     * Applies search constraint for the derived code column.
     */
    public static function applySearch(Builder $builder, string $search, Department $department, string $type, ?string $idColumn = null): Builder
    {
        $idColumn ??= $builder->getModel()->qualifyColumn('id');
        $parsed = self::parseId($search, $department, $type);

        if ($parsed === false) {
            return $builder->whereRaw('0 = 1');
        }

        if (is_int($parsed)) {
            return $builder->where($idColumn, $parsed);
        }

        return $builder->whereRaw('0 = 1');
    }
}
