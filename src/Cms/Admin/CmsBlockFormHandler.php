<?php

declare(strict_types=1);

namespace App\Cms\Admin;

use App\Cms\CmsBlockRendererRegistry;
use App\Cms\CmsImageUploader;
use App\Entity\Cms\CmsBlock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class CmsBlockFormHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CmsBlockRendererRegistry $registry,
        private readonly CmsImageUploader $imageUploader,
    ) {
    }

    public function save(FormInterface $form, CmsBlock $block, bool $new): bool
    {
        if (!$form->isSubmitted() || !$form->isValid()) {
            return false;
        }
        $oldImages = $this->configurationImages($block->getConfiguration());
        $uploadedImages = [];
        $configuration = [];
        foreach ($form as $name => $field) {
            if (in_array($name, ['locale', 'type', 'placement', 'position', 'enabled', 'image'], true)) {
                continue;
            }
            $configuration[$name] = $field->getData();
        }
        $upload = $form->has('image') ? $form->get('image')->getData() : null;
        if ($form->has('image') && isset($block->getConfiguration()['image'])) {
            $configuration['image'] = $block->getConfiguration()['image'];
        }
        if ($upload instanceof UploadedFile) {
            $this->upload($upload, $form->get('image'), $configuration, 'image', $uploadedImages);
        }
        if ($form->has('items') && in_array($block->getType(), ['gallery', 'homepage_industries'], true)) {
            $configuration['items'] = [];
            foreach ($form->get('items') as $itemForm) {
                $itemData = is_array($itemForm->getData()) ? $itemForm->getData() : [];
                $existing = $itemData['existingImage'] ?? null;
                $image = is_string($existing) && in_array($existing, $oldImages, true) ? $existing : null;
                $itemUpload = $itemForm->get('image')->getData();
                if ($itemUpload instanceof UploadedFile) {
                    $target = [];
                    $this->upload($itemUpload, $itemForm->get('image'), $target, 'image', $uploadedImages);
                    $image = $target['image'] ?? $image;
                }
                unset($itemData['existingImage'], $itemData['image']);
                $configuration['items'][] = ['image' => $image] + array_map(static fn (mixed $value): mixed => is_string($value) ? trim($value) : $value, $itemData);
            }
        }
        foreach ($this->registry->validate($block->getType(), $configuration) as $error) {
            $form->addError(new FormError($error));
        }
        if (!$form->isValid()) {
            $this->deleteImages($uploadedImages);
            return false;
        }
        $block->setConfiguration($configuration);
        try {
            if ($new) {
                $this->entityManager->persist($block);
            }
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            $this->deleteImages($uploadedImages);
            throw $exception;
        }
        $this->deleteImages(array_diff($oldImages, $this->configurationImages($configuration)));
        return true;
    }

    public function deleteBlockImages(CmsBlock $block): void { $this->deleteImages($this->configurationImages($block->getConfiguration())); }

    /** @param array<string, mixed> $configuration @return list<string> */
    public function configurationImages(array $configuration): array
    {
        $images = [];
        if (isset($configuration['image']) && is_string($configuration['image'])) $images[] = $configuration['image'];
        foreach ((array) ($configuration['items'] ?? []) as $item) {
            if (is_array($item) && isset($item['image']) && is_string($item['image'])) $images[] = $item['image'];
        }
        return array_values(array_unique($images));
    }

    /** @param array<string, mixed> $configuration @param list<string> $uploaded */
    private function upload(UploadedFile $file, FormInterface $field, array &$configuration, string $key, array &$uploaded): void
    {
        try { $configuration[$key] = $this->imageUploader->upload($file); $uploaded[] = $configuration[$key]; }
        catch (\InvalidArgumentException|\RuntimeException $exception) { $field->addError(new FormError($exception->getMessage())); }
    }

    /** @param iterable<string> $images */ private function deleteImages(iterable $images): void { foreach ($images as $image) $this->imageUploader->delete($image); }
}
