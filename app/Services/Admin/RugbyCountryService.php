<?php

namespace App\Services\Admin;

use Illuminate\Support\{Collection, Str};
use Illuminate\Support\Facades\DB;

use App\Models\RugbyCountry;
use App\Repositories\Contracts\Admin\RugbyCountryRepositoryInterface;

class RugbyCountryService
{
    public function __construct(
        private readonly RugbyCountryRepositoryInterface $rugbyCountryRepository
    ) {}

    public function getAll(array $filters = [])
    {
        return $this->rugbyCountryRepository->getAll($filters);
    }

    public function getAllForReordering(): Collection {
        return $this->rugbyCountryRepository->getAllForReordering();
    }

    public function getStats(): array
    {
        return $this->rugbyCountryRepository->getStats();
    }

    public function create(array $data): RugbyCountry
    {
        return DB::transaction(function () use ($data) {
            $name = trim((string) $data['name']);
            $key = filled($data['key'] ?? null) ? Str::slug((string) $data['key'], '_') : Str::slug($name, '_');
            $flag = filled($data['flag'] ?? null) ? Str::slug((string) $data['flag'], '_') : $key;

            return $this->rugbyCountryRepository->create([
                'slug' => strtolower(trim((string) $data['slug'])),
                'key' => $key,
                'name' => $name,
                'name_translations' => build_country_name_translations($data['slug'], $name, $data['name_translations'] ?? []),
                'flag' => $flag,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $this->rugbyCountryRepository->getNextSortOrder(),
            ]);
        });
    }

    public function update(RugbyCountry $rugbyCountry, array $data): RugbyCountry
    {
        return DB::transaction(function () use ($rugbyCountry, $data) {
            $name = trim((string) $data['name']);
            $key = filled($data['key'] ?? null) ? Str::slug((string) $data['key'], '_') : Str::slug($name, '_');
            $flag = filled($data['flag'] ?? null) ? Str::slug((string) $data['flag'], '_') : $key;

            return $this->rugbyCountryRepository->update($rugbyCountry, [
                'slug' => strtolower(trim((string) $data['slug'])),
                'key' => $key,
                'name' => $name,
                'name_translations' => build_country_name_translations($data['slug'], $name, $data['name_translations'] ?? []),
                'flag' => $flag,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    public function delete(RugbyCountry $rugbyCountry): bool
    {
        return DB::transaction(function () use ($rugbyCountry) {
            return $this->rugbyCountryRepository->delete($rugbyCountry);
        });
    }

    public function toggleStatus(RugbyCountry $rugbyCountry): RugbyCountry
    {
        return DB::transaction(function () use ($rugbyCountry) {
            return $this->rugbyCountryRepository->update($rugbyCountry, [
                'is_active' => ! (bool) $rugbyCountry->is_active,
            ]);
        });
    }

    public function reorder(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            $this->rugbyCountryRepository->reorder($ids);
        });
    }
}
