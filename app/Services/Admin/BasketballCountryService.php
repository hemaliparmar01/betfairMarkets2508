<?php

namespace App\Services\Admin;

use Illuminate\Support\{Collection, Str};
use Illuminate\Support\Facades\DB;

use App\Models\BasketballCountry;
use App\Repositories\Contracts\Admin\BasketballCountryRepositoryInterface;

class BasketballCountryService
{
    public function __construct(
        private readonly BasketballCountryRepositoryInterface $basketballCountryRepository
    ) {}

    public function getAll(array $filters = [])
    {
        return $this->basketballCountryRepository->getAll($filters);
    }

    public function getAllForReordering(): Collection {
        return $this->basketballCountryRepository->getAllForReordering();
    }

    public function getStats(): array
    {
        return $this->basketballCountryRepository->getStats();
    }

    public function create(array $data): BasketballCountry
    {
        return DB::transaction(function () use ($data) {
            $name = trim((string) $data['name']);
            $key = filled($data['key'] ?? null) ? Str::slug((string) $data['key'], '_') : Str::slug($name, '_');
            $flag = filled($data['flag'] ?? null) ? Str::slug((string) $data['flag'], '_') : $key;

            return $this->basketballCountryRepository->create([
                'slug' => strtolower(trim((string) $data['slug'])),
                'key' => $key,
                'name' => $name,
                'name_translations' => build_country_name_translations($data['slug'], $name, $data['name_translations'] ?? []),
                'flag' => $flag,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $this->basketballCountryRepository->getNextSortOrder(),
            ]);
        });
    }

    public function update(BasketballCountry $basketballCountry, array $data): BasketballCountry
    {
        return DB::transaction(function () use ($basketballCountry, $data) {
            $name = trim((string) $data['name']);
            $key = filled($data['key'] ?? null) ? Str::slug((string) $data['key'], '_') : Str::slug($name, '_');
            $flag = filled($data['flag'] ?? null) ? Str::slug((string) $data['flag'], '_') : $key;

            return $this->basketballCountryRepository->update($basketballCountry, [
                'slug' => strtolower(trim((string) $data['slug'])),
                'key' => $key,
                'name' => $name,
                'name_translations' => build_country_name_translations($data['slug'], $name, $data['name_translations'] ?? []),
                'flag' => $flag,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    public function delete(BasketballCountry $basketballCountry): bool
    {
        return DB::transaction(function () use ($basketballCountry) {
            return $this->basketballCountryRepository->delete($basketballCountry);
        });
    }

    public function toggleStatus(BasketballCountry $basketballCountry): BasketballCountry
    {
        return DB::transaction(function () use ($basketballCountry) {
            return $this->basketballCountryRepository->update($basketballCountry, [
                'is_active' => ! (bool) $basketballCountry->is_active,
            ]);
        });
    }

    public function reorder(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            $this->basketballCountryRepository->reorder($ids);
        });
    }
}
