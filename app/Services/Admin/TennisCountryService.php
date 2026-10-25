<?php

namespace App\Services\Admin;

use Illuminate\Support\{Collection, Str};
use Illuminate\Support\Facades\DB;

use App\Models\TennisCountry;
use App\Repositories\Contracts\Admin\TennisCountryRepositoryInterface;

class TennisCountryService
{
    public function __construct(
        private readonly TennisCountryRepositoryInterface $tennisCountryRepository
    ) {}

    public function getAll(array $filters = [])
    {
        return $this->tennisCountryRepository->getAll($filters);
    }

    public function getAllForReordering(): Collection {
        return $this->tennisCountryRepository->getAllForReordering();
    }

    public function getStats(): array
    {
        return $this->tennisCountryRepository->getStats();
    }

    public function create(array $data): TennisCountry
    {
        return DB::transaction(function () use ($data) {
            $name = trim((string) $data['name']);
            $key = filled($data['key'] ?? null)
                ? Str::slug((string) $data['key'], '_')
                : Str::slug($name, '_');
            $flag = filled($data['flag'] ?? null)
                ? Str::slug((string) $data['flag'], '_')
                : $key;

            return $this->tennisCountryRepository->create([
                'slug' => strtolower(trim((string) $data['slug'])),
                'key' => $key,
                'name' => $name,
                'name_translations' => build_country_name_translations($data['slug'], $name, $data['name_translations'] ?? []),
                'flag' => $flag,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $this->tennisCountryRepository->getNextSortOrder(),
            ]);
        });
    }

    public function update(TennisCountry $tennisCountry, array $data): TennisCountry
    {
        return DB::transaction(function () use ($tennisCountry, $data) {
            $name = trim((string) $data['name']);
            $key = filled($data['key'] ?? null)
                ? Str::slug((string) $data['key'], '_')
                : Str::slug($name, '_');
            $flag = filled($data['flag'] ?? null)
                ? Str::slug((string) $data['flag'], '_')
                : $key;

            return $this->tennisCountryRepository->update($tennisCountry, [
                'slug' => strtolower(trim((string) $data['slug'])),
                'key' => $key,
                'name' => $name,
                'name_translations' => build_country_name_translations($data['slug'], $name, $data['name_translations'] ?? []),
                'flag' => $flag,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    public function delete(TennisCountry $tennisCountry): bool
    {
        return DB::transaction(function () use ($tennisCountry) {
            return $this->tennisCountryRepository->delete($tennisCountry);
        });
    }

    public function toggleStatus(TennisCountry $tennisCountry): TennisCountry
    {
        return DB::transaction(function () use ($tennisCountry) {
            return $this->tennisCountryRepository->update($tennisCountry, [
                'is_active' => ! (bool) $tennisCountry->is_active,
            ]);
        });
    }

    public function reorder(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            $this->tennisCountryRepository->reorder($ids);
        });
    }
}
