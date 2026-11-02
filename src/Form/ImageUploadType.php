<?php

namespace Hamid\ImageUploadBundle\Form;

use Hamid\ImageUploadBundle\ImageUploader;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ImageUploadType extends AbstractType
{
    public function __construct(private readonly ImageUploader $uploader)
    {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $constraint = $this->uploader->getImageConstraint();
        $resolver->setDefaults([
            'constraints' => [$constraint],
            'attr' => ['accept' => implode(',', $constraint->mimeTypes)],
        ]);
    }

    public function getParent(): string
    {
        return FileType::class;
    }
}
