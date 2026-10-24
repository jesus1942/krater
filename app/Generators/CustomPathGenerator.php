<?php

namespace Crater\Generators;

use Crater\Models\Estimate;
use Crater\Models\Invoice;
use Crater\Models\Payment;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class CustomPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->getBasePath($media).'/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getBasePath($media).'/conversations/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getBasePath($media).'/responsive-images/';
    }

    /*
     * Get a unique base path for the given media.
     */
    protected function getBasePath(Media $media): string
    {
        $folderName = null;

        if ($media->model_type == Invoice::class) {
            $folderName = 'Invoices';
        } elseif ($media->model_type == Estimate::class) {
            $folderName = 'Estimates';
        } elseif ($media->model_type == Payment::class) {
            $folderName = 'Payments';
        } else {
            $folderName = $media->getKey();
        }

        // Un comprobante privado tiene su propio directorio. Dos empresas
        // pueden usar el mismo numero: nunca compartir ni sobrescribir su PDF.
        // Conservar el camino legacy para leer las copias ya guardadas.
        return $media->disk === 'finance_private'
            ? $folderName.'/'.$media->getKey()
            : $folderName;
    }
}
