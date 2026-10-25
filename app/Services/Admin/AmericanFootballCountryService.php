<?php

namespace App\Services\Admin;

use Illuminate\Support\{Collection, Str};
use Illuminate\Support\Facades\DB;

use App\Models\AmericanFootballCountry;
use App\Repositories\Contracts\Admin\AmericanFootballCountryRepositoryInterface;

class AmericanFootballCountryService
{
    public function __construct(
        private readonly AmericanFootballCountryRepositoryInterface $americanFootballCountryRepository
    ) {}

    public function getAllForReordering(): Collection {
        return $this->americanFootballCountryRepository->getAllForReordering();
    }

    public function getAll(array $filters = [])
    {
        return $this->americanFootballCountryRepository->getAll($filters);
    }

    public function getStats(): array
    {
        return $this->americanFootballCountryRepository->getStats();
    }

    public function create(array $data): AmericanFootballCountry
    {
        return DB::transaction(function () use ($data) {
            $name = trim((string) $data['name']);
            $key = filled($data['key'] ?? null) ? Str::slug((string) $data['key'], '_') : Str::slug($name, '_');
            $flag = filled($data['flag'] ?? null) ? Str::slug((string) $data['flag'], '_') : $key;

            return $this->americanFootballCountryRepository->create([
                'slug' => strtolower(trim((string) $data['slug'])),
                'key' => $key,
                'name' => $name,
                'name_translations' => build_country_name_translations($data['slug'], $name, $data['name_translations'] ?? []),
                'flag' => $flag,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $this->americanFootballCountryRepository->getNextSortOrder(),
            ]);
        });
    }

    public function update(AmericanFootballCountry $americanFootballCountry, array $data): AmericanFootballCountry
    {
        return DB::transaction(function () use ($americanFootballCountry, $data) {
            $name = trim((string) $data['name']);
            $key = filled($data['key'] ?? null) ? Str::slug((string) $data['key'], '_') : Str::slug($name, '_');
            $flag = filled($data['flag'] ?? null) ? Str::slug((string) $data['flag'], '_') : $key;

            return $this->americanFootballCountryRepository->update($americanFootballCountry, [
                'slug' => strtolower(trim((string) $data['slug'])),
                'key' => $key,
                'name' => $name,
                'name_translations' => build_country_name_translations($data['slug'], $name, $data['name_translations'] ?? []),
                'flag' => $flag,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    public function delete(AmericanFootballCountry $americanFootballCountry): bool
    {
        return DB::transaction(function () use ($americanFootballCountry) {
            return $this->americanFootballCountryRepository->delete($americanFootballCountry);
        });
    }

    public function toggleStatus(AmericanFootballCountry $americanFootballCountry): AmericanFootballCountry
    {
        return DB::transaction(function () use ($americanFootballCountry) {
            return $this->americanFootballCountryRepository->update($americanFootballCountry, [
                'is_active' => ! (bool) $americanFootballCountry->is_active,
            ]);
        });
    }

    public function reorder(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            $this->americanFootballCountryRepository->reorder($ids);
        });
    }
}
