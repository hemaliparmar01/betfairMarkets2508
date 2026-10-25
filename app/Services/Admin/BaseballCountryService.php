<?php

namespace App\Services\Admin;

use Illuminate\Support\{Collection, Str};
use Illuminate\Support\Facades\DB;

use App\Models\BaseballCountry;
use App\Repositories\Contracts\Admin\BaseballCountryRepositoryInterface;

class BaseballCountryService
{
    public function __construct(
        private readonly BaseballCountryRepositoryInterface $baseballCountryRepository
    ) {}

    public function getAllForReordering(): Collection {
        return $this->baseballCountryRepository->getAllForReordering();
    }

    public function getAll(array $filters = [])
    {
        return $this->baseballCountryRepository->getAll($filters);
    }

    public function getStats(): array
    {
        return $this->baseballCountryRepository->getStats();
    }

    public function create(array $data): BaseballCountry
    {
        return DB::transaction(function () use ($data) {
            $name = trim((string) $data['name']);
            $key = filled($data['key'] ?? null) ? Str::slug((string) $data['key'], '_') : Str::slug($name, '_');
            $flag = filled($data['flag'] ?? null) ? Str::slug((string) $data['flag'], '_') : $key;

            return $this->baseballCountryRepository->create([
                'slug' => strtolower(trim((string) $data['slug'])),
                'key' => $key,
                'name' => $name,
                'name_translations' => build_country_name_translations($data['slug'], $name, $data['name_translations'] ?? []),
                'flag' => $flag,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $this->baseballCountryRepository->getNextSortOrder(),
            ]);
        });
    }

    public function update(BaseballCountry $baseballCountry, array $data): BaseballCountry
    {
        return DB::transaction(function () use ($baseballCountry, $data) {
            $name = trim((string) $data['name']);
            $key = filled($data['key'] ?? null) ? Str::slug((string) $data['key'], '_') : Str::slug($name, '_');
            $flag = filled($data['flag'] ?? null) ? Str::slug((string) $data['flag'], '_') : $key;

            return $this->baseballCountryRepository->update($baseballCountry, [
                'slug' => strtolower(trim((string) $data['slug'])),
                'key' => $key,
                'name' => $name,
                'name_translations' => build_country_name_translations($data['slug'], $name, $data['name_translations'] ?? []),
                'flag' => $flag,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    public function delete(BaseballCountry $baseballCountry): bool
    {
        return DB::transaction(function () use ($baseballCountry) {
            return $this->baseballCountryRepository->delete($baseballCountry);
        });
    }

    public function toggleStatus(BaseballCountry $baseballCountry): BaseballCountry
    {
        return DB::transaction(function () use ($baseballCountry) {
            return $this->baseballCountryRepository->update($baseballCountry, [
                'is_active' => ! (bool) $baseballCountry->is_active,
            ]);
        });
    }

    public function reorder(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            $this->baseballCountryRepository->reorder($ids);
        });
    }
}
