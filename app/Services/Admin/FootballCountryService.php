<?php

namespace App\Services\Admin;

use Illuminate\Support\{Collection, Str};
use Illuminate\Support\Facades\DB;

use App\Models\FootballCountry;
use App\Repositories\Contracts\Admin\FootballCountryRepositoryInterface;

class FootballCountryService
{
    public function __construct(
        private readonly FootballCountryRepositoryInterface $footballCountryRepository
    ) {}

    public function getAllForReordering(): Collection {
        return $this->footballCountryRepository->getAllForReordering();
    }

    public function getAll(array $filters = [])
    {
        return $this->footballCountryRepository->getAll($filters);
    }

    public function getStats(): array
    {
        return $this->footballCountryRepository->getStats();
    }

    public function create(array $data): FootballCountry
    {
        return DB::transaction(function () use ($data) {
            $name = trim((string) $data['name']);
            $key = filled($data['key'] ?? null) ? Str::slug((string) $data['key'], '_') : Str::slug($name, '_');
            $flag = filled($data['flag'] ?? null) ? Str::slug((string) $data['flag'], '_') : $key;

            return $this->footballCountryRepository->create([
                'slug' => strtolower(trim((string) $data['slug'])),
                'key' => $key,
                'name' => $name,
                'name_translations' => build_country_name_translations($data['slug'], $name, $data['name_translations'] ?? []),
                'flag' => $flag,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $this->footballCountryRepository->getNextSortOrder(),
            ]);
        });
    }

    public function update(FootballCountry $footballCountry, array $data): FootballCountry
    {
        return DB::transaction(function () use ($footballCountry, $data) {
            $name = trim((string) $data['name']);
            $key = filled($data['key'] ?? null) ? Str::slug((string) $data['key'], '_') : Str::slug($name, '_');
            $flag = filled($data['flag'] ?? null) ? Str::slug((string) $data['flag'], '_') : $key;

            return $this->footballCountryRepository->update($footballCountry, [
                'slug' => strtolower(trim((string) $data['slug'])),
                'key' => $key,
                'name' => $name,
                'name_translations' => build_country_name_translations($data['slug'], $name, $data['name_translations'] ?? []),
                'flag' => $flag,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    public function delete(FootballCountry $footballCountry): bool
    {
        return DB::transaction(function () use ($footballCountry) {
            return $this->footballCountryRepository->delete($footballCountry);
        });
    }

    public function toggleStatus(FootballCountry $footballCountry): FootballCountry
    {
        return DB::transaction(function () use ($footballCountry) {
            return $this->footballCountryRepository->update($footballCountry, [
                'is_active' => ! (bool) $footballCountry->is_active,
            ]);
        });
    }

    public function reorder(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            $this->footballCountryRepository->reorder($ids);
        });
    }
}
