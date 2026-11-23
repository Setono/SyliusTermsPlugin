<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Functional;

/**
 * For tests that upload the image fixtures into the test application's storage of Uploaded images, which is the
 * plugin's default: public/media/terms. The storage isn't rolled back with the database, so the tests delete what they
 * stored
 */
trait StoresImageFixturesTrait
{
    private const FIXTURES = __DIR__ . '/../Fixtures/images';

    private const STORAGE_DIRECTORY = __DIR__ . '/../Application/public/media/terms';

    /** The names the PNG and JPEG fixtures are stored under: the SHA-256 of their bytes, and their type's extension */
    private const PNG = 'a2cb41b89e07d4fe99464fdd159b8bdd0e9360ca50c0386151fba6efde07593b.png';

    private const JPEG = '9477a0aeadd5a51a8e300f7c30b779d9632aec4e48b5f3c31ca3af3a05ad95d2.jpg';

    private static function deleteStoredFixtures(): void
    {
        foreach ([self::PNG, self::JPEG] as $name) {
            if (is_file(self::STORAGE_DIRECTORY . '/' . $name)) {
                unlink(self::STORAGE_DIRECTORY . '/' . $name);
            }
        }
    }

    /**
     * The names of the images in the storage
     *
     * @return list<string>
     */
    private static function getStoredImages(): array
    {
        $paths = glob(self::STORAGE_DIRECTORY . '/*');
        self::assertIsArray($paths);

        return array_map(basename(...), $paths);
    }
}
