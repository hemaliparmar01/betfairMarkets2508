<?php

namespace App\Services\Admin;

use Illuminate\Support\{Collection, Str};
use Illuminate\Support\Facades\DB;

use App\Models\DartsCountry;
use App\Repositories\Contracts\Admin\DartsCountryRepositoryInterface;

class DartsCountryService
{
    public function __construct(
        private readonly DartsCountryRepositoryInterface $dartsCountryRepository
    ) {}

    public function getAll(array $filters = [])
    {
        return $this->dartsCountryRepository->getAll($filters);
    }

    public function getAllForReordering(): Collection {
        return $this->dartsCountryRepository->getAllForReordering();
    }

    public function getStats(): array
    {
        return $this->dartsCountryRepository->getStats();
    }

    public function create(array $data): DartsCountry
    {
        return DB::transaction(function () use ($data) {
            $name = trim((string) $data['name']);
            $key = filled($data['key'] ?? null) ? Str::slug((string) $data['key'], '_') : Str::slug($name, '_');
            $flag = filled($data['flag'] ?? null) ? Str::slug((string) $data['flag'], '_') : $key;

            return $this->dartsCountryRepository->create([
                'slug' => strtolower(trim((string) $data['slug'])),
                'key' => $key,
                'name' => $name,
                'name_translations' => build_country_name_translations($data['slug'], $name, $data['name_translations'] ?? []),
                'flag' => $flag,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $this->dartsCountryRepository->getNextSortOrder(),
            ]);
        });
    }

    public function update(DartsCountry $dartsCountry, array $data): DartsCountry
    {
        return DB::transaction(function () use ($dartsCountry, $data) {
            $name = trim((string) $data['name']);
            $key = filled($data['key'] ?? null) ? Str::slug((string) $data['key'], '_') : Str::slug($name, '_');
            $flag = filled($data['flag'] ?? null) ? Str::slug((string) $data['flag'], '_') : $key;

            return $this->dartsCountryRepository->update($dartsCountry, [
                'slug' => strtolower(trim((string) $data['slug'])),
                'key' => $key,
                'name' => $name,
                'name_translations' => build_country_name_translations($data['slug'], $name, $data['name_translations'] ?? []),
                'flag' => $flag,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    public function delete(DartsCountry $dartsCountry): bool
    {
        return DB::transaction(function () use ($dartsCountry) {
            return $this->dartsCountryRepository->delete($dartsCountry);
        });
    }

    public function toggleStatus(DartsCountry $dartsCountry): DartsCountry
    {
        return DB::transaction(function () use ($dartsCountry) {
            return $this->dartsCountryRepository->update($dartsCountry, [
                'is_active' => ! (bool) $dartsCountry->is_active,
            ]);
        });
    }

    public function reorder(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            $this->dartsCountryRepository->reorder($ids);
        });
    }
}
