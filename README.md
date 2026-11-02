# Image Upload Bundle

A small Symfony bundle for validated JPEG, PNG and WebP uploads. It has its own
Composer metadata, namespace and tests, and does not depend on the movie application,
Doctrine, Twig, authentication or Monolog. PHP 8.4+, Fileinfo and GD are required.
The development test runner currently requires PHP 8.5 because it uses PHPUnit 13.

## Install from GitHub

The publication target is `https://github.com/HamidZ11/image-upload-bundle`.
After that repository contains the reviewed `v1.0.0` tag, add this VCS repository
to a Symfony project's `composer.json`:

```json
{
    "repositories": [{
        "type": "vcs",
        "url": "https://github.com/HamidZ11/image-upload-bundle.git"
    }],
    "require": {"hamid/image-upload-bundle": "^1.0"}
}
```

Run `composer update hamid/image-upload-bundle --prefer-dist --no-scripts`, then
configure the bundle before running `php bin/console cache:clear`. Composer derives
the stable package version from the Git tag. No path repository, global
`minimum-stability` change or Packagist listing is necessary. This download cannot
be verified until the repository and tag are public.

Ensure `config/bundles.php` registers:

```php
Hamid\ImageUploadBundle\ImageUploadBundle::class => ['all' => true],
```

Create `config/packages/image_upload.yaml`:

```yaml
image_upload:
    directory: '%kernel.project_dir%/public/uploads/avatars'
    max_size: '2M'
    max_pixels: 4000000
```

The directory is required; size and pixel limits default to `5M` and 10000000.
Use a trusted, writable directory. Public uploads must be served as static files,
without script execution. PHP's `upload_max_filesize` and `post_max_size` must be
large enough for the chosen limits. Log PHP warnings rather than displaying them
inside HTML responses.

## Use the form type and uploader

The reusable form type extends Symfony's `FileType`:

```php
use Hamid\ImageUploadBundle\Form\ImageUploadType;

$builder->add('avatar', ImageUploadType::class, [
    'mapped' => false,
    'required' => false,
]);
```

After the application authorizes and validates the form, pass its `UploadedFile`
to an autowired `Hamid\ImageUploadBundle\ImageUploader`:

```php
$filename = $uploader->upload($uploadedFile);
```

`upload()` returns a random basename with an extension selected from detected file
content. It validates size, detected MIME type, dimensions and decoded image data,
even when called directly without a form. Invalid files and storage failures throw
Symfony's `FileException`. `getImageConstraint()` supplies the same configured
Symfony constraint used by both the uploader and form type.

`remove(?string $filename)` removes only generated basenames inside the configured
directory. Missing files and null names are harmless. Unsafe names and filesystem
failures are logged when a logger is available. The bundle does not require Monolog.

The application owns permissions, CSRF protection, entity fields, public URLs,
replacement/deletion policy and database transactions. File and database operations
are not atomic: clean up new files after a failed save and remove old ones only
after a successful replacement. There are no automatic Doctrine listeners, routes,
resizing, cloud storage adapters or application-specific entities.

## Run independently

From this package directory:

```bash
composer install
php vendor/bin/phpunit
composer validate --strict
composer audit
```

The tests boot a separate minimal Symfony host with this bundle, use temporary
storage, and need neither the movie application's kernel/vendor directory nor a
database. They cover actual image formats, forged/corrupt/oversized files, direct
service validation, unique names, safe removal and host-specific configuration.

This package is prepared for its own public repository; publication and tagging
are manual steps. See `PUBLICATION.md` in the coursework application for the exact
hand-off. The bundle is licensed under the [MIT licence](LICENSE). The movie
application's own licence is unchanged.
