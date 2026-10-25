<?php

namespace App\Services\Admin;

use Illuminate\Support\{Collection, Str};
use Illuminate\Support\Facades\DB;

use App\Models\EsportCountry;
use App\Repositories\Contracts\Admin\EsportCountryRepositoryInterface;

class EsportCountryService
{
    public function __construct(
        private readonly EsportCountryRepositoryInterface $esportCountryRepository
    ) {}

    public function getAll(array $filters = [])
    {
        return $this->esportCountryRepository->getAll($filters);
    }

    public function getAllForReordering(): Collection {
        return $this->esportCountryRepository->getAllForReordering();
    }

    public function getStats(): array
    {
        return $this->esportCountryRepository->getStats();
    }

    public function create(array $data): EsportCountry
    {
        return DB::transaction(function () use ($data) {
            $name = trim((string) $data['name']);
            $key = filled($data['key'] ?? null) ? Str::slug((string) $data['key'], '_') : Str::slug($name, '_');
            $flag = filled($data['flag'] ?? null) ? Str::slug((string) $data['flag'], '_') : $key;

            return $this->esportCountryRepository->create([
                'slug' => strtolower(trim((string) $data['slug'])),
                'key' => $key,
                'name' => $name,
                'name_translations' => build_country_name_translations($data['slug'], $name, $data['name_translations'] ?? []),
                'flag' => $flag,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $this->esportCountryRepository->getNextSortOrder(),
            ]);
        });
    }

    public function update(EsportCountry $esportCountry, array $data): EsportCountry
    {
        return DB::transaction(function () use ($esportCountry, $data) {
            $name = trim((string) $data['name']);
            $key = filled($data['key'] ?? null) ? Str::slug((string) $data['key'], '_') : Str::slug($name, '_');
            $flag = filled($data['flag'] ?? null) ? Str::slug((string) $data['flag'], '_') : $key;

            return $this->esportCountryRepository->update($esportCountry, [
                'slug' => strtolower(trim((string) $data['slug'])),
                'key' => $key,
                'name' => $name,
                'name_translations' => build_country_name_translations($data['slug'], $name, $data['name_translations'] ?? []),
                'flag' => $flag,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    public function delete(EsportCountry $esportCountry): bool
    {
        return DB::transaction(function () use ($esportCountry) {
            return $this->esportCountryRepository->delete($esportCountry);
        });
    }

    public function toggleStatus(EsportCountry $esportCountry): EsportCountry
    {
        return DB::transaction(function () use ($esportCountry) {
            return $this->esportCountryRepository->update($esportCountry, [
                'is_active' => ! (bool) $esportCountry->is_active,
            ]);
        });
    }

    public function reorder(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            $this->esportCountryRepository->reorder($ids);
        });
    }
}
