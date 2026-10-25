<?php

namespace App\Services\Admin;

use Illuminate\Support\{Collection, Str};
use Illuminate\Support\Facades\DB;

use App\Models\HandballCountry;
use App\Repositories\Contracts\Admin\HandballCountryRepositoryInterface;

class HandballCountryService
{
    public function __construct(
        private readonly HandballCountryRepositoryInterface $handballCountryRepository
    ) {}

    public function getAll(array $filters = [])
    {
        return $this->handballCountryRepository->getAll($filters);
    }

    public function getAllForReordering(): Collection {
        return $this->handballCountryRepository->getAllForReordering();
    }

    public function getStats(): array
    {
        return $this->handballCountryRepository->getStats();
    }

    public function create(array $data): HandballCountry
    {
        return DB::transaction(function () use ($data) {
            $name = trim((string) $data['name']);
            $key = filled($data['key'] ?? null) ? Str::slug((string) $data['key'], '_') : Str::slug($name, '_');
            $flag = filled($data['flag'] ?? null) ? Str::slug((string) $data['flag'], '_') : $key;

            return $this->handballCountryRepository->create([
                'slug' => strtolower(trim((string) $data['slug'])),
                'key' => $key,
                'name' => $name,
                'name_translations' => build_country_name_translations($data['slug'], $name, $data['name_translations'] ?? []),
                'flag' => $flag,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $this->handballCountryRepository->getNextSortOrder(),
            ]);
        });
    }

    public function update(HandballCountry $handballCountry, array $data): HandballCountry
    {
        return DB::transaction(function () use ($handballCountry, $data) {
            $name = trim((string) $data['name']);
            $key = filled($data['key'] ?? null) ? Str::slug((string) $data['key'], '_') : Str::slug($name, '_');
            $flag = filled($data['flag'] ?? null) ? Str::slug((string) $data['flag'], '_') : $key;

            return $this->handballCountryRepository->update($handballCountry, [
                'slug' => strtolower(trim((string) $data['slug'])),
                'key' => $key,
                'name' => $name,
                'name_translations' => build_country_name_translations($data['slug'], $name, $data['name_translations'] ?? []),
                'flag' => $flag,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    public function delete(HandballCountry $handballCountry): bool
    {
        return DB::transaction(function () use ($handballCountry) {
            return $this->handballCountryRepository->delete($handballCountry);
        });
    }

    public function toggleStatus(HandballCountry $handballCountry): HandballCountry
    {
        return DB::transaction(function () use ($handballCountry) {
            return $this->handballCountryRepository->update($handballCountry, [
                'is_active' => ! (bool) $handballCountry->is_active,
            ]);
        });
    }

    public function reorder(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            $this->handballCountryRepository->reorder($ids);
        });
    }
}
