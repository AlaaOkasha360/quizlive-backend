<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Support\Facades\Storage;

trait MediaStorageTrait
{
    /**
     * Save media (images or videos) to the specified folder.
     *
     * @param  mixed  $media  A single file or an array of files.
     * @param  string  $folderName  Folder name to save the media.
     * @return array|string Paths of the saved files or single path if not array.
     */
    public function saveMedia($media, $folderName)
    {
        $mediaPaths = [];
        if (is_array($media)) {
            foreach ($media as $item) {
                $mediaPaths[] = $item->store($folderName, 'public');
            }

            return $mediaPaths;
        }

        return $media->store($folderName, 'public');
    }

    /**
     * Delete media (images or videos) from storage.
     *
     * @param  mixed  $media  Paths of files to delete (string or array).
     * @return bool True if successfully deleted, otherwise false.
     */
    public function deleteMedia($media): bool
    {
        return Storage::disk('public')->delete($media);
    }

    private function extractFilenameFromResponse($response, string $fileUrl): string
    {
        $disposition = $response->header('Content-Disposition');

        if ($disposition && preg_match('/filename="?([^"]+)"?/i', $disposition, $matches)) {
            return $matches[1];
        }

        $path = parse_url($fileUrl, PHP_URL_PATH);
        $name = basename($path);

        if ($name && $name !== '/') {
            return $name;
        }

        // 3️⃣ Absolute fallback
        return 'downloaded_' . now()->timestamp . '.dat';
    }
}
