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

        $images = collect($product->get('images'));
        if ($images->isEmpty()) {
            return;
        }

        $images->collapse()->each(
            fn ($image, $key) => $this->saveImage($image, $key),
        );

        if ($this->images->isNotEmpty()) {
            // @todo Create separate job for variant images
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
                ->first(function ($option) use ($colorOption) {
                    $color = collect($option['color'])->first();
                    $colorValue = $color['name']->getValue()->get('en')->getValue();
                    return $colorValue === $colorOption;
                });

            if (! $colorOption || ! $matchedOption) {
                return;
            }

            $imageIds = $this->images
                ->filter(fn ($image, $imageId) => in_array($image, $matchedOption['images']))
                ->keys();

            foreach ($imageIds as $key => $imageId) {
                $imagesToSync[$imageId] = [
                    'primary' => $key === 0,
                    'position' => $key+1,
                ];
            }

            $variant->images()->sync($imagesToSync);
        });
    }

    protected function saveImage(string $image, int $key): void
    {
        if ($this->imageExists($image)) {
            return;
        }

        if (!Http::get($image)->ok()) {
            Log::driver('slack')->warning("Image {$image} could not be downloaded.");
			return;
		}

        $imageFilename = parse_url($image, PHP_URL_QUERY);
        $media = $this->getProductModel()
            ->addMediaFromUrl($image)
            ->setFileName($imageFilename)
            ->toMediaCollection('images');

        $media->setCustomProperty('primary', $key === 0);
        $media->save();

        if ($media->id) {
            $this->images->put($media->id, $image);
        }
    }

    protected function imageExists(string $image): bool
    {
        $imageFilename = parse_url($image, PHP_URL_QUERY);

        $mediaCollection = $this->getProductModel()->getMedia('images')->mapWithKeys(
            fn (Media $media) => [
                $media->id => Str::of($media->file_name)->value(),
            ],
        );

        $matchedMediaId = $mediaCollection->filter(
            fn ($filename) => $filename === $imageFilename,
        )->keys()->first();

        if ($matchedMediaId) {
            $this->images->put($matchedMediaId, $image);
        }

        return !!$matchedMediaId;
    }
}
