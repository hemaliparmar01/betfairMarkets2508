<?php

namespace App\Services\Admin;

use Illuminate\Support\{Collection, Str};
use Illuminate\Support\Facades\DB;

use App\Models\MmaCountry;
use App\Repositories\Contracts\Admin\MmaCountryRepositoryInterface;

class MmaCountryService
{
    public function __construct(
        private readonly MmaCountryRepositoryInterface $mmaCountryRepository
    ) {}

    public function getAll(array $filters = [])
    {
        return $this->mmaCountryRepository->getAll($filters);
    }

    public function getAllForReordering(): Collection {
        return $this->mmaCountryRepository->getAllForReordering();
    }

    public function getStats(): array
    {
        return $this->mmaCountryRepository->getStats();
    }

    public function create(array $data): MmaCountry
    {
        return DB::transaction(function () use ($data) {
            $name = trim((string) $data['name']);
            $key = filled($data['key'] ?? null) ? Str::slug((string) $data['key'], '_') : Str::slug($name, '_');
            $flag = filled($data['flag'] ?? null) ? Str::slug((string) $data['flag'], '_') : $key;

            return $this->mmaCountryRepository->create([
                'slug' => strtolower(trim((string) $data['slug'])),
                'key' => $key,
                'name' => $name,
                'name_translations' => build_country_name_translations($data['slug'], $name, $data['name_translations'] ?? []),
                'flag' => $flag,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $this->mmaCountryRepository->getNextSortOrder(),
            ]);
        });
    }

    public function update(MmaCountry $mmaCountry, array $data): MmaCountry
    {
        return DB::transaction(function () use ($mmaCountry, $data) {
            $name = trim((string) $data['name']);
            $key = filled($data['key'] ?? null) ? Str::slug((string) $data['key'], '_') : Str::slug($name, '_');
            $flag = filled($data['flag'] ?? null) ? Str::slug((string) $data['flag'], '_') : $key;

            return $this->mmaCountryRepository->update($mmaCountry, [
                'slug' => strtolower(trim((string) $data['slug'])),
                'key' => $key,
                'name' => $name,
                'name_translations' => build_country_name_translations($data['slug'], $name, $data['name_translations'] ?? []),
                'flag' => $flag,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    public function delete(MmaCountry $mmaCountry): bool
    {
        return DB::transaction(function () use ($mmaCountry) {
            return $this->mmaCountryRepository->delete($mmaCountry);
        });
    }

    public function toggleStatus(MmaCountry $mmaCountry): MmaCountry
    {
        return DB::transaction(function () use ($mmaCountry) {
            return $this->mmaCountryRepository->update($mmaCountry, [
                'is_active' => ! (bool) $mmaCountry->is_active,
            ]);
        });
    }

    public function reorder(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            $this->mmaCountryRepository->reorder($ids);
        });
    }
}
