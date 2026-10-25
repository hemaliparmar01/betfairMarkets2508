<?php

namespace App\Services\Admin;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};

use App\Repositories\Eloquent\Admin\SiteSettingRepository;

class SiteSettingService
{
    public function __construct(private readonly SiteSettingRepository $siteSettingRepository) {}

    public function getAll(): array {
        return $this->siteSettingRepository->getAll();
    }

    public function update(array $data, array $files = []): void {
        DB::transaction(function () use ($data, $files) {
            $currentSettings = $this->getAll();

            foreach (['logo', 'dark_logo', 'favicon','logo_translations','dark_logo_translations'] as $field) {
                $uploadedFile = $files[$field] ?? null;
                if(is_array($uploadedFile)) {
                    $newArray = [];
                    foreach ($uploadedFile as $key => $uFile) {
                        $newArray[$key] = upload_file_into_server($uFile,'site-settings',$currentSettings[$field] ?? null);
                    }
                    $data[$field] = json_encode($newArray);
                } else {
                    if (! $uploadedFile instanceof UploadedFile) {
                        continue;
                    }
                    $data[$field] = upload_file_into_server($files[$field],'site-settings',$currentSettings[$field] ?? null);
                }
            }
            $this->siteSettingRepository->update($data);
        });
    }
}
