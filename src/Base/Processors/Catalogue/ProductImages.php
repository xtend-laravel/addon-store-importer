<?php

namespace XtendLunar\Addons\StoreImporter\Base\Processors\Catalogue;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lunar\Models\ProductVariant;
use XtendLunar\Addons\StoreImporter\Base\Processors\Catalogue\Concerns\InteractsWithProductModel;
use XtendLunar\Addons\StoreImporter\Base\Processors\Processor;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use XtendLunar\Addons\StoreImporter\Models\StoreImporterResourceModel;

class ProductImages extends Processor
{
    use InteractsWithProductModel;

    protected Collection $images;

    public function process(Collection $product, ?StoreImporterResourceModel $resourceModel = null): void
    {
        $this->images = collect();
        $this->setProductModel($product->get('productModel'));

        $images = collect($product->get('product_images') ?? $product->get('images') ?? []);
        if ($images->isEmpty()) {
            return;
        }

        $images->collapse()->each(
            fn ($image, $fileKey) => $this->saveImage($image, $fileKey),
        );

        if ($this->images->isNotEmpty()) {
            $this->variantImages($product);
        }
    }

    protected function variantImages(Collection $product): void
    {
        $variants = $this->getProductModel()->variants()->where('base', false)->get();

        $variants->each(function (ProductVariant $variant) use ($product) {
            $colorOption = $variant->values->first(
                fn ($value) => $value->option->handle === 'color',
            )?->translate('name');

            $matchedOption = collect($product->get('options'))
                ->first(function ($option) use ($colorOption, $variant) {
                    $color = collect($option['color'])->first();
                    $colorValue = $color['name']->getValue()->get('en')->getValue();
                    return $colorValue === $colorOption;
                });

            if (! $colorOption || ! $matchedOption) {
                return;
            }

            $variant->update([
                'primary' => collect($matchedOption['color'])->first()['primary_variant'] ?? false,
            ]);

            $imageIds = $this->images
                ->filter(fn ($fileKey, $imageId) => collect($matchedOption['images'])->keys()->contains($fileKey))
                ->keys();

            $imagesToSync = [];
            foreach ($imageIds as $key => $imageId) {
                $imagesToSync[$imageId] = [
                    'primary' => $key === 0,
                    'position' => $key+1,
                ];
            }

            $variant->images()->sync($imagesToSync);
        });
    }

    protected function saveImage(string $image, string $fileKey): void
    {
        if ($this->imageExists($fileKey)) {
            return;
        }

        if (!Http::get($image)->ok()) {
            Log::driver('slack')->warning("Image {$image} could not be downloaded.");
			return;
		}

        $media = $this->getProductModel()
            ->addMediaFromUrl($image)
            ->setFileName($fileKey)
            ->toMediaCollection('images');

        $media->setCustomProperty('primary', $this->images->isEmpty());
        $media->save();

        if ($media->id) {
            $this->images->put($media->id, $fileKey);
        }
    }

    protected function imageExists(string $fileKey): bool
    {
        $mediaCollection = $this->getProductModel()->getMedia('images')->mapWithKeys(
            fn (Media $media) => [
                $media->id => Str::of($media->file_name)->value(),
            ],
        );

        $matchedMediaId = $mediaCollection->filter(
            fn ($filename) => $filename === $fileKey,
        )->keys()->first();

        if ($matchedMediaId) {
            $this->images->put($matchedMediaId, $fileKey);
        }

        return !!$matchedMediaId;
    }
}
