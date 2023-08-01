<?php

namespace XtendLunar\Addons\StoreImporter\Base\Processors\Catalogue;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use XtendLunar\Addons\StoreImporter\Base\Processors\Catalogue\Concerns\InteractsWithProductModel;
use XtendLunar\Addons\StoreImporter\Base\Processors\Processor;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use XtendLunar\Addons\StoreImporter\Models\StoreImporterResourceModel;

class ProductImages extends Processor
{
    use InteractsWithProductModel;

    public function process(Collection $product, StoreImporterResourceModel $resourceModel): void
    {
        $this->setProductModel($product->get('productModel'));

        $images = collect($product->get('images'));
        if ($images->isEmpty()) {
            return;
        }

        $images->each(
            fn ($image, $key) => $this->saveImage($image, $key),
        );
    }

    protected function saveImage(string $image, int $key): void
    {
        if (!Http::get($image)->ok()) {
			return;
		}

        if ($this->imageExists($image)) {
            return;
        }

        $media = $this->getProductModel()
            ->addMediaFromUrl($image)
            ->toMediaCollection('images');

        $media->setCustomProperty('primary', $key === 0);
        $media->save();
    }

    protected function imageExists(string $image): bool
    {
        // @todo Allow to replace image on force update?
        $filename = basename(parse_url($image, PHP_URL_PATH));

        return $this->getProductModel()->getMedia('products')->map(
            fn (Media $media) => Str::of($media->file_name)->beforeLast('.')
        )->contains($filename);
    }
}
