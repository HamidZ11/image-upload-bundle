<?php

namespace Hamid\ImageUploadBundle\Tests;

use Hamid\ImageUploadBundle\Form\ImageUploadType;
use Hamid\ImageUploadBundle\ImageUploader;
use Hamid\ImageUploadBundle\Tests\Fixtures\HostKernel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ImageUploadBundleTest extends TestCase
{
    private HostKernel $kernel;
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/image-upload-bundle-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700, true);
        $this->kernel = new HostKernel($this->directory);
        $this->kernel->boot();
    }

    protected function tearDown(): void
    {
        if (isset($this->kernel)) {
            $this->kernel->shutdown();
        }
        if (isset($this->directory)) {
            (new Filesystem())->remove($this->directory);
        }
    }

    #[DataProvider('formats')]
    public function testIndependentHostCanValidateStoreAndRemoveImages(string $format, string $extension): void
    {
        $file = $this->image($format);
        $form = $this->kernel->getContainer()->get('test.form_factory')->create(ImageUploadType::class);
        $form->submit($file);
        self::assertTrue($form->isValid());
        $uploader = $this->uploader();
        $first = $uploader->upload($file);
        $second = $uploader->upload($this->image($format));
        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\.'.$extension.'\z/', $first);
        self::assertNotSame($first, $second);
        self::assertFileExists($this->directory.'/images/'.$first);
        self::assertFileExists($this->directory.'/images/'.$second);
        $uploader->remove($first);
        $uploader->remove($first);
        $uploader->remove(null);
        self::assertFileDoesNotExist($this->directory.'/images/'.$first);
        self::assertFileExists($this->directory.'/images/'.$second);
        self::assertArrayHasKey('ImageUploadBundle', $this->kernel->getBundles());
        self::assertArrayNotHasKey('DoctrineBundle', $this->kernel->getBundles());
    }

    public static function formats(): iterable
    {
        yield 'JPEG' => ['jpeg', 'jpg'];
        yield 'PNG' => ['png', 'png'];
        yield 'WebP' => ['webp', 'webp'];
    }

    #[DataProvider('invalidImages')]
    public function testInvalidImageIsRejectedByFormAndDirectUploader(string $kind): void
    {
        $file = $this->image('png');
        match ($kind) {
            'text' => file_put_contents($file->getPathname(), '<?php echo "invalid";'),
            'svg' => file_put_contents($file->getPathname(), '<svg xmlns="http://www.w3.org/2000/svg" width="2" height="2"/>'),
            'corrupt' => file_put_contents($file->getPathname(), substr(file_get_contents($file->getPathname()), 0, 40)),
            'large' => file_put_contents($file->getPathname(), str_repeat('x', 5_000_001), FILE_APPEND),
        };
        $form = $this->kernel->getContainer()->get('test.form_factory')->create(ImageUploadType::class);
        $form->submit($file);
        self::assertFalse($form->isValid());

        try {
            $this->uploader()->upload($file);
            self::fail('The direct uploader must also enforce image validation.');
        } catch (FileException) {
            self::assertFileExists($file->getPathname());
            self::assertSame([], glob($this->directory.'/images/*'));
        }
    }

    public static function invalidImages(): iterable
    {
        yield 'forged content type' => ['text'];
        yield 'SVG' => ['svg'];
        yield 'corrupt image' => ['corrupt'];
        yield 'oversized file' => ['large'];
    }

    #[DataProvider('customLimits')]
    public function testAnotherHostCanConfigureItsOwnLimits(string $maxSize, int $maxPixels): void
    {
        $this->kernel->shutdown();
        $this->kernel = new HostKernel($this->directory.'/custom', $maxSize, $maxPixels);
        $this->kernel->boot();
        $file = $this->image('png');
        $form = $this->kernel->getContainer()->get('test.form_factory')->create(ImageUploadType::class);
        $form->submit($file);
        self::assertFalse($form->isValid());
        $this->expectException(FileException::class);
        $this->uploader()->upload($file);
    }

    public static function customLimits(): iterable
    {
        yield 'different file size' => ['50', 10_000_000];
        yield 'different pixel limit' => ['5M', 1];
    }

    public function testRemovalCannotEscapeConfiguredStorage(): void
    {
        $outside = $this->directory.'/outside.png';
        file_put_contents($outside, 'Keep this file');
        foreach (['../outside.png', '..\\outside.png', $outside, 'outside.png'] as $name) {
            $this->uploader()->remove($name);
        }
        self::assertFileExists($outside);
    }

    private function uploader(): ImageUploader
    {
        return $this->kernel->getContainer()->get('test.image_uploader');
    }

    private function image(string $format): UploadedFile
    {
        $path = tempnam($this->directory, 'source-');
        $image = imagecreatetruecolor(2, 2);
        match ($format) {
            'jpeg' => imagejpeg($image, $path),
            'png' => imagepng($image, $path),
            'webp' => imagewebp($image, $path),
        };

        return new UploadedFile($path, '../../unsafe.php', 'image/png', test: true);
    }
}
