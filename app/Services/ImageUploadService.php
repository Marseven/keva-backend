<?php
// app/Services/ImageUploadService.php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageUploadService
{
    private ImageManager $imageManager;
    private array $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    private int $maxFileSize = 5 * 1024 * 1024; // 5MB

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
    }

    /**
     * Upload une image avec redimensionnement automatique
     */
    public function uploadProductImage(UploadedFile $file, string $folder = 'products'): array
    {
        $this->validateImage($file);

        $filename = $this->generateFilename($file);
        $path = "{$folder}/" . date('Y/m');

        // Créer les différentes tailles
        $sizes = [
            'original' => null,
            'large' => ['width' => 800, 'height' => 800],
            'medium' => ['width' => 400, 'height' => 400],
            'thumb' => ['width' => 150, 'height' => 150],
        ];

        $uploadedFiles = [];

        foreach ($sizes as $sizeName => $dimensions) {
            $sizedFilename = $sizeName === 'original' ? $filename : $sizeName . '_' . $filename;
            $fullPath = $path . '/' . $sizedFilename;

            $image = $this->imageManager->read($file->getPathname());

            if ($dimensions) {
                $image = $image->resize($dimensions['width'], $dimensions['height'], function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
            }

            // Optimiser la qualité
            $quality = $sizeName === 'thumb' ? 80 : 90;
            $imageData = $image->toJpeg($quality);

            Storage::disk('public')->put($fullPath, $imageData);

            $uploadedFiles[$sizeName] = $fullPath;
        }

        return [
            'filename' => $filename,
            'path' => $path,
            'sizes' => $uploadedFiles,
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'original_name' => $file->getClientOriginalName(),
        ];
    }

    /**
     * Upload plusieurs images
     */
    public function uploadMultipleImages(array $files, string $folder = 'products'): array
    {
        $uploadedImages = [];

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $uploadedImages[] = $this->uploadProductImage($file, $folder);
            }
        }

        return $uploadedImages;
    }

    /**
     * Supprimer une image et toutes ses variantes
     */
    public function deleteProductImage(string $imagePath): bool
    {
        $pathInfo = pathinfo($imagePath);
        $directory = $pathInfo['dirname'];
        $filename = $pathInfo['filename'] . '.' . $pathInfo['extension'];

        // Supprimer toutes les tailles
        $sizes = ['original', 'large', 'medium', 'thumb'];

        foreach ($sizes as $size) {
            $sizedFilename = $size === 'original' ? $filename : $size . '_' . $filename;
            $fullPath = $directory . '/' . $sizedFilename;

            if (Storage::disk('public')->exists($fullPath)) {
                Storage::disk('public')->delete($fullPath);
            }
        }

        return true;
    }

    /**
     * Valider une image
     */
    private function validateImage(UploadedFile $file): void
    {
        if (!in_array($file->getMimeType(), $this->allowedMimes)) {
            throw new \InvalidArgumentException('Type de fichier non supporté. Utilisez JPG, PNG ou WebP.');
        }

        if ($file->getSize() > $this->maxFileSize) {
            throw new \InvalidArgumentException('Le fichier est trop volumineux. Taille maximum : 5MB.');
        }
    }

    /**
     * Générer un nom de fichier unique
     */
    private function generateFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        return Str::uuid() . '.' . $extension;
    }

    /**
     * Obtenir l'URL publique d'une image
     */
    public function getImageUrl(string $imagePath, string $size = 'medium'): string
    {
        if ($size === 'original') {
            return Storage::disk('public')->url($imagePath);
        }

        $pathInfo = pathinfo($imagePath);
        $directory = $pathInfo['dirname'];
        $filename = $pathInfo['filename'] . '.' . $pathInfo['extension'];
        $sizedPath = $directory . '/' . $size . '_' . $filename;

        return Storage::disk('public')->url($sizedPath);
    }
}
