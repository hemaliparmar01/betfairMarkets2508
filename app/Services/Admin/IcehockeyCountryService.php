<?php

namespace App\Services\Admin;

use Illuminate\Support\{Collection, Str};
use Illuminate\Support\Facades\DB;

use App\Models\IcehockeyCountry;
use App\Repositories\Contracts\Admin\IcehockeyCountryRepositoryInterface;

class IcehockeyCountryService
{
    public function __construct(
        private readonly IcehockeyCountryRepositoryInterface $icehockeyCountryRepository
    ) {}

    public function getAll(array $filters = [])
    {
        return $this->icehockeyCountryRepository->getAll($filters);
    }

    public function getAllForReordering(): Collection {
        return $this->icehockeyCountryRepository->getAllForReordering();
    }

    public function getStats(): array
    {
        return $this->icehockeyCountryRepository->getStats();
    }

    public function create(array $data): IcehockeyCountry
    {
        return DB::transaction(function () use ($data) {
            $name = trim((string) $data['name']);
            $key = filled($data['key'] ?? null) ? Str::slug((string) $data['key'], '_') : Str::slug($name, '_');
            $flag = filled($data['flag'] ?? null) ? Str::slug((string) $data['flag'], '_') : $key;

            return $this->icehockeyCountryRepository->create([
                'slug' => strtolower(trim((string) $data['slug'])),
                'key' => $key,
                'name' => $name,
                'name_translations' => build_country_name_translations($data['slug'], $name, $data['name_translations'] ?? []),
                'flag' => $flag,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $this->icehockeyCountryRepository->getNextSortOrder(),
            ]);
        });
    }

    public function update(IcehockeyCountry $icehockeyCountry, array $data): IcehockeyCountry
    {
        return DB::transaction(function () use ($icehockeyCountry, $data) {
            $name = trim((string) $data['name']);
            $key = filled($data['key'] ?? null) ? Str::slug((string) $data['key'], '_') : Str::slug($name, '_');
            $flag = filled($data['flag'] ?? null) ? Str::slug((string) $data['flag'], '_') : $key;

            return $this->icehockeyCountryRepository->update($icehockeyCountry, [
                'slug' => strtolower(trim((string) $data['slug'])),
                'key' => $key,
                'name' => $name,
                'name_translations' => build_country_name_translations($data['slug'], $name, $data['name_translations'] ?? []),
                'flag' => $flag,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    public function delete(IcehockeyCountry $icehockeyCountry): bool
    {
        return DB::transaction(function () use ($icehockeyCountry) {
            return $this->icehockeyCountryRepository->delete($icehockeyCountry);
        });
    }

    public function toggleStatus(IcehockeyCountry $icehockeyCountry): IcehockeyCountry
    {
        return DB::transaction(function () use ($icehockeyCountry) {
            return $this->icehockeyCountryRepository->update($icehockeyCountry, [
                'is_active' => ! (bool) $icehockeyCountry->is_active,
            ]);
        });
    }

    public function reorder(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            $this->icehockeyCountryRepository->reorder($ids);
        });
    }
}
