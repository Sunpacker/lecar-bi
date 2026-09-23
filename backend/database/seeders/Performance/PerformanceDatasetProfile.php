<?php

declare(strict_types=1);

namespace Database\Seeders\Performance;

use InvalidArgumentException;

enum PerformanceDatasetProfile: string
{
    case Small = 'small';
    case Large = 'large';

    public static function fromName(string $name): self
    {
        $profile = self::tryFrom(strtolower(trim($name)));
        if ($profile === null) {
            $valid = implode(', ', array_map(fn (self $case) => $case->value, self::cases()));
            throw new InvalidArgumentException("Unknown performance dataset profile '{$name}'. Valid profiles: {$valid}.");
        }

        return $profile;
    }

    public function ordersCount(): int
    {
        return match ($this) {
            self::Small => 500,
            self::Large => 100_000,
        };
    }

    public function itemsCount(): int
    {
        return match ($this) {
            self::Small => 1_500,
            self::Large => 300_000,
        };
    }

    public function inventorySnapshotsCount(): int
    {
        return match ($this) {
            self::Small => 2_500,
            self::Large => 500_000,
        };
    }

    public function deliveriesCount(): int
    {
        return match ($this) {
            self::Small => 1_000,
            self::Large => 200_000,
        };
    }

    public function productsCount(): int
    {
        return match ($this) {
            self::Small => 200,
            self::Large => 10_000,
        };
    }

    public function categoriesCount(): int
    {
        return 10;
    }

    public function warehousesCount(): int
    {
        return 5;
    }

    public function suppliersCount(): int
    {
        return 50;
    }

    public function regionsCount(): int
    {
        return 8;
    }

    public function brandsCount(): int
    {
        return 10;
    }

    public function salesChannelsCount(): int
    {
        return 4;
    }
}
