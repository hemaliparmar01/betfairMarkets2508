<?php

namespace App\Services\Admin;

use Illuminate\Support\{Collection, Str};
use Illuminate\Support\Facades\DB;

use App\Models\BoxingCountry;
use App\Repositories\Contracts\Admin\BoxingCountryRepositoryInterface;

class BoxingCountryService
{
    public function __construct(
        private readonly BoxingCountryRepositoryInterface $boxingCountryRepository
    ) {}

    public function getAll(array $filters = [])
    {
        return $this->boxingCountryRepository->getAll($filters);
    }

    public function getAllForReordering(): Collection {
        return $this->boxingCountryRepository->getAllForReordering();
    }

    public function getStats(): array
    {
        return $this->boxingCountryRepository->getStats();
    }

    public function create(array $data): BoxingCountry
    {
        return DB::transaction(function () use ($data) {
            $name = trim((string) $data['name']);
            $key = filled($data['key'] ?? null) ? Str::slug((string) $data['key'], '_') : Str::slug($name, '_');
            $flag = filled($data['flag'] ?? null) ? Str::slug((string) $data['flag'], '_') : $key;

            return $this->boxingCountryRepository->create([
                'slug' => strtolower(trim((string) $data['slug'])),
                'key' => $key,
                'name' => $name,
                'name_translations' => build_country_name_translations($data['slug'], $name, $data['name_translations'] ?? []),
                'flag' => $flag,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $this->boxingCountryRepository->getNextSortOrder(),
            ]);
        });
    }

    public function update(BoxingCountry $boxingCountry, array $data): BoxingCountry
    {
        return DB::transaction(function () use ($boxingCountry, $data) {
            $name = trim((string) $data['name']);
            $key = filled($data['key'] ?? null) ? Str::slug((string) $data['key'], '_') : Str::slug($name, '_');
            $flag = filled($data['flag'] ?? null) ? Str::slug((string) $data['flag'], '_') : $key;

            return $this->boxingCountryRepository->update($boxingCountry, [
                'slug' => strtolower(trim((string) $data['slug'])),
                'key' => $key,
                'name' => $name,
                'name_translations' => build_country_name_translations($data['slug'], $name, $data['name_translations'] ?? []),
                'flag' => $flag,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    public function delete(BoxingCountry $boxingCountry): bool
    {
        return DB::transaction(function () use ($boxingCountry) {
            return $this->boxingCountryRepository->delete($boxingCountry);
        });
    }

    public function toggleStatus(BoxingCountry $boxingCountry): BoxingCountry
    {
        return DB::transaction(function () use ($boxingCountry) {
            return $this->boxingCountryRepository->update($boxingCountry, [
                'is_active' => ! (bool) $boxingCountry->is_active,
            ]);
        });
    }

    public function reorder(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            $this->boxingCountryRepository->reorder($ids);
        });
    }
}
