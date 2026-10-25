<?php

namespace App\Services\Admin;

use Illuminate\Support\{Collection, Str};
use Illuminate\Support\Facades\DB;

use App\Models\CricketCountry;
use App\Repositories\Contracts\Admin\CricketCountryRepositoryInterface;

class CricketCountryService
{
    public function __construct(
        private readonly CricketCountryRepositoryInterface $cricketCountryRepository
    ) {}

    public function getAll(array $filters = [])
    {
        return $this->cricketCountryRepository->getAll($filters);
    }

    public function getAllForReordering(): Collection {
        return $this->cricketCountryRepository->getAllForReordering();
    }

    public function getStats(): array
    {
        return $this->cricketCountryRepository->getStats();
    }

    public function create(array $data): CricketCountry
    {
        return DB::transaction(function () use ($data) {
            $name = trim((string) $data['name']);
            $key = filled($data['key'] ?? null) ? Str::slug((string) $data['key'], '_') : Str::slug($name, '_');
            $flag = filled($data['flag'] ?? null) ? Str::slug((string) $data['flag'], '_') : $key;

            return $this->cricketCountryRepository->create([
                'slug' => strtolower(trim((string) $data['slug'])),
                'key' => $key,
                'name' => $name,
                'name_translations' => build_country_name_translations($data['slug'], $name, $data['name_translations'] ?? []),
                'flag' => $flag,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $this->cricketCountryRepository->getNextSortOrder(),
            ]);
        });
    }

    public function update(CricketCountry $cricketCountry, array $data): CricketCountry
    {
        return DB::transaction(function () use ($cricketCountry, $data) {
            $name = trim((string) $data['name']);
            $key = filled($data['key'] ?? null) ? Str::slug((string) $data['key'], '_') : Str::slug($name, '_');
            $flag = filled($data['flag'] ?? null) ? Str::slug((string) $data['flag'], '_') : $key;

            return $this->cricketCountryRepository->update($cricketCountry, [
                'slug' => strtolower(trim((string) $data['slug'])),
                'key' => $key,
                'name' => $name,
                'name_translations' => build_country_name_translations($data['slug'], $name, $data['name_translations'] ?? []),
                'flag' => $flag,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    public function delete(CricketCountry $cricketCountry): bool
    {
        return DB::transaction(function () use ($cricketCountry) {
            return $this->cricketCountryRepository->delete($cricketCountry);
        });
    }

    public function toggleStatus(CricketCountry $cricketCountry): CricketCountry
    {
        return DB::transaction(function () use ($cricketCountry) {
            return $this->cricketCountryRepository->update($cricketCountry, [
                'is_active' => ! (bool) $cricketCountry->is_active,
            ]);
        });
    }

    public function reorder(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            $this->cricketCountryRepository->reorder($ids);
        });
    }
}
