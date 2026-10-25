<?php

namespace App\Services\Admin;

use Illuminate\Support\{Collection, Str};
use Illuminate\Support\Facades\DB;

use App\Models\VolleyballCountry;
use App\Repositories\Contracts\Admin\VolleyballCountryRepositoryInterface;

class VolleyballCountryService
{
    public function __construct(
        private readonly VolleyballCountryRepositoryInterface $volleyballCountryRepository
    ) {}

    public function getAll(array $filters = [])
    {
        return $this->volleyballCountryRepository->getAll($filters);
    }

    public function getAllForReordering(): Collection {
        return $this->volleyballCountryRepository->getAllForReordering();
    }

    public function getStats(): array
    {
        return $this->volleyballCountryRepository->getStats();
    }

    public function create(array $data): VolleyballCountry
    {
        return DB::transaction(function () use ($data) {
            $name = trim((string) $data['name']);
            $key = filled($data['key'] ?? null) ? Str::slug((string) $data['key'], '_') : Str::slug($name, '_');
            $flag = filled($data['flag'] ?? null) ? Str::slug((string) $data['flag'], '_') : $key;

            return $this->volleyballCountryRepository->create([
                'slug' => strtolower(trim((string) $data['slug'])),
                'key' => $key,
                'name' => $name,
                'name_translations' => build_country_name_translations($data['slug'], $name, $data['name_translations'] ?? []),
                'flag' => $flag,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $this->volleyballCountryRepository->getNextSortOrder(),
            ]);
        });
    }

    public function update(VolleyballCountry $volleyballCountry, array $data): VolleyballCountry
    {
        return DB::transaction(function () use ($volleyballCountry, $data) {
            $name = trim((string) $data['name']);
            $key = filled($data['key'] ?? null) ? Str::slug((string) $data['key'], '_') : Str::slug($name, '_');
            $flag = filled($data['flag'] ?? null) ? Str::slug((string) $data['flag'], '_') : $key;

            return $this->volleyballCountryRepository->update($volleyballCountry, [
                'slug' => strtolower(trim((string) $data['slug'])),
                'key' => $key,
                'name' => $name,
                'name_translations' => build_country_name_translations($data['slug'], $name, $data['name_translations'] ?? []),
                'flag' => $flag,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    public function delete(VolleyballCountry $volleyballCountry): bool
    {
        return DB::transaction(function () use ($volleyballCountry) {
            return $this->volleyballCountryRepository->delete($volleyballCountry);
        });
    }

    public function toggleStatus(VolleyballCountry $volleyballCountry): VolleyballCountry
    {
        return DB::transaction(function () use ($volleyballCountry) {
            return $this->volleyballCountryRepository->update($volleyballCountry, [
                'is_active' => ! (bool) $volleyballCountry->is_active,
            ]);
        });
    }

    public function reorder(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            $this->volleyballCountryRepository->reorder($ids);
        });
    }
}
