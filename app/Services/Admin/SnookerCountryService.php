<?php

namespace App\Services\Admin;

use Illuminate\Support\{Collection, Str};
use Illuminate\Support\Facades\DB;

use App\Models\SnookerCountry;
use App\Repositories\Contracts\Admin\SnookerCountryRepositoryInterface;

class SnookerCountryService
{
    public function __construct(
        private readonly SnookerCountryRepositoryInterface $snookerCountryRepository
    ) {}

    public function getAll(array $filters = [])
    {
        return $this->snookerCountryRepository->getAll($filters);
    }

    public function getAllForReordering(): Collection {
        return $this->snookerCountryRepository->getAllForReordering();
    }

    public function getStats(): array
    {
        return $this->snookerCountryRepository->getStats();
    }

    public function create(array $data): SnookerCountry
    {
        return DB::transaction(function () use ($data) {
            $name = trim((string) $data['name']);
            $key = filled($data['key'] ?? null) ? Str::slug((string) $data['key'], '_') : Str::slug($name, '_');
            $flag = filled($data['flag'] ?? null) ? Str::slug((string) $data['flag'], '_') : $key;

            return $this->snookerCountryRepository->create([
                'slug' => strtolower(trim((string) $data['slug'])),
                'key' => $key,
                'name' => $name,
                'name_translations' => build_country_name_translations($data['slug'], $name, $data['name_translations'] ?? []),
                'flag' => $flag,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $this->snookerCountryRepository->getNextSortOrder(),
            ]);
        });
    }

    public function update(SnookerCountry $snookerCountry, array $data): SnookerCountry
    {
        return DB::transaction(function () use ($snookerCountry, $data) {
            $name = trim((string) $data['name']);
            $key = filled($data['key'] ?? null) ? Str::slug((string) $data['key'], '_') : Str::slug($name, '_');
            $flag = filled($data['flag'] ?? null) ? Str::slug((string) $data['flag'], '_') : $key;

            return $this->snookerCountryRepository->update($snookerCountry, [
                'slug' => strtolower(trim((string) $data['slug'])),
                'key' => $key,
                'name' => $name,
                'name_translations' => build_country_name_translations($data['slug'], $name, $data['name_translations'] ?? []),
                'flag' => $flag,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    public function delete(SnookerCountry $snookerCountry): bool
    {
        return DB::transaction(function () use ($snookerCountry) {
            return $this->snookerCountryRepository->delete($snookerCountry);
        });
    }

    public function toggleStatus(SnookerCountry $snookerCountry): SnookerCountry
    {
        return DB::transaction(function () use ($snookerCountry) {
            return $this->snookerCountryRepository->update($snookerCountry, [
                'is_active' => ! (bool) $snookerCountry->is_active,
            ]);
        });
    }

    public function reorder(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            $this->snookerCountryRepository->reorder($ids);
        });
    }
}
