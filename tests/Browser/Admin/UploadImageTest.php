<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Browser\Admin;

use PHPUnit\Framework\Attributes\Test;
use Playwright\Locator\LocatorInterface;
use Playwright\Page\PageInterface;
use Playwright\Symfony\Test\PlaywrightTestCase;
use function Playwright\Testing\expect;
use Setono\SyliusTermsPlugin\Tests\Functional\CreatesTermsTrait;
use Setono\SyliusTermsPlugin\Tests\Functional\StoresImageFixturesTrait;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;

/**
 * Uploading images into the Content by dropping, pasting or picking them. Drops and pastes are dispatched as the
 * browser dispatches them, with the files in their DataTransfer
 */
final class UploadImageTest extends PlaywrightTestCase
{
    use CreatesTermsTrait;
    use StoresImageFixturesTrait;

    /** A script that makes a File of a file() array: the fixture's bytes, under the name, padded with zeros to the size */
    private const MAKE_FILE = '({ name, type, base64, size }) => new File(
        [Uint8Array.from(atob(base64), (character) => character.charCodeAt(0)), new Uint8Array(Math.max(0, size - atob(base64).length))],
        name,
        { type },
    )';

    protected function setUp(): void
    {
        parent::setUp();

        self::deleteStoredFixtures();

        /** @var UserRepositoryInterface<AdminUserInterface> $repository */
        $repository = self::getContainer()->get('sylius.repository.admin_user');
        $admin = $repository->findOneBy(['username' => 'sylius']);
        self::assertInstanceOf(AdminUserInterface::class, $admin);

        $this->loginUser($admin, 'admin');
    }

    protected function tearDown(): void
    {
        self::deleteStoredFixtures();

        parent::tearDown();
    }

    #[Test]
    public function a_dropped_image_gets_a_placeholder_then_its_markdown_which_the_preview_shows_and_the_terms_saves(): void
    {
        $terms = self::createTerms('cookie_policy');
        self::getEntityManager()->clear();

        $page = $this->open(sprintf('/admin/terms/%d/edit', (int) $terms->getId()));
        $textarea = $page->locator('[data-test-content="en_US"]');
        $textarea->fill("We bake cookies.\n\n\n\nEnjoy them.");
        // The cursor goes between the two paragraphs
        self::placeCursor($textarea, \strlen("We bake cookies.\n\n"));
        self::recordContentEdits($page, 'en_US');

        self::drop($page, 'en_US', [self::file('cookie.png')]);

        $expected = sprintf("We bake cookies.\n\n![cookie](/media/terms/%s)\n\nEnjoy them.", self::PNG);
        expect($textarea)->toHaveValue($expected);
        // Each edit fired an input event: the placeholder at the cursor, then the image's Markdown in its place
        self::assertSame(
            ["We bake cookies.\n\n![Uploading cookie.png…]()\n\nEnjoy them.", $expected],
            self::getRecordedContentEdits($page),
        );
        self::assertSame([], self::getErrors($page, 'en_US'));

        // The URL serves the uploaded file, byte for byte
        self::assertSame(
            base64_encode((string) file_get_contents(self::FIXTURES . '/cookie.png')),
            $page->evaluate('async url => { const bytes = new Uint8Array(await (await fetch(url)).arrayBuffer()); return btoa(String.fromCharCode(...bytes)); }', '/media/terms/' . self::PNG),
        );

        // Clicking the tab takes the focus from the textarea, which re-renders the Live form with the image
        $page->locator('[data-test-content-preview-tab="en_US"]')->click();
        $image = $page->locator('[data-test-content-preview="en_US"] img');
        expect($image)->toHaveAttribute('src', '/media/terms/' . self::PNG);
        expect($image)->toHaveAttribute('alt', 'cookie');
        $page->waitForFunction('() => document.querySelector(\'[data-test-content-preview="en_US"] img\').naturalWidth > 0');

        $page->locator('[data-test-update-changes-button]')->click();
        expect($page->locator('[data-test-sylius-flash-message-type="success"]'))->toBeVisible();

        self::assertSame($expected, self::reloadTerms($terms)->getTranslation('en_US')->getContent());
        self::assertSame([], $page->pageErrors(), 'The Terms form threw JavaScript errors');
    }

    /**
     * Each image gets its own placeholder, in order, separated by blank lines. Each edit is an undo step of its own
     */
    #[Test]
    public function images_dropped_together_get_a_placeholder_each_and_undo_takes_the_edits_back(): void
    {
        $terms = self::createTerms('cookie_policy');
        self::getEntityManager()->clear();

        $page = $this->open(sprintf('/admin/terms/%d/edit', (int) $terms->getId()));
        $textarea = $page->locator('[data-test-content="en_US"]');
        $textarea->fill('');
        self::recordContentEdits($page, 'en_US');

        self::drop($page, 'en_US', [self::file('cookie.png'), self::file('cookie.jpg', 'photo [1].jpg')]);

        $placeholders = "![Uploading cookie.png…]()\n\n![Uploading photo \\[1\\].jpg…]()";
        $first = sprintf("![cookie](/media/terms/%s)\n\n![Uploading photo \\[1\\].jpg…]()", self::PNG);
        $expected = sprintf("![cookie](/media/terms/%s)\n\n![photo \\[1\\]](/media/terms/%s)", self::PNG, self::JPEG);
        expect($textarea)->toHaveValue($expected);
        self::assertSame([$placeholders, $first, $expected], self::getRecordedContentEdits($page));

        // The textarea kept the focus, so the shortcut undoes in it
        $page->keyboard()->press('ControlOrMeta+z');
        expect($textarea)->toHaveValue($first);
        $page->keyboard()->press('ControlOrMeta+z');
        expect($textarea)->toHaveValue($placeholders);
        $page->keyboard()->press('ControlOrMeta+z');
        expect($textarea)->toHaveValue('');

        self::assertSame([], $page->pageErrors(), 'The Terms form threw JavaScript errors');
    }

    /**
     * The second locale is in a collapsed item of the accordion. The Preview tab re-renders the Live form first
     */
    #[Test]
    public function pasting_and_picking_an_image_work_in_a_second_locale_after_a_live_rerender(): void
    {
        $terms = self::createTerms('cookie_policy', localeCodes: ['en_US', 'de_DE']);
        self::getEntityManager()->clear();

        $page = $this->open(sprintf('/admin/terms/%d/edit', (int) $terms->getId()));
        $page->locator('[data-test-terms-translations-accordion="de_DE"]')->click();
        $textarea = $page->locator('[data-test-content="de_DE"]');
        $textarea->fill("Wir backen Kekse.\n\n");

        $page->locator('[data-test-content-preview-tab="de_DE"]')->click();
        expect($page->locator('[data-test-content-preview="de_DE"]'))->toContainText('Wir backen Kekse.');
        $page->locator('[data-test-content-write-tab="de_DE"]')->click();
        expect($textarea)->toBeVisible();

        self::placeCursor($textarea, \strlen("Wir backen Kekse.\n\n"));
        self::paste($page, 'de_DE', self::file('cookie.png'));
        $pasted = sprintf("Wir backen Kekse.\n\n![cookie](/media/terms/%s)", self::PNG);
        expect($textarea)->toHaveValue($pasted);

        $page->keyboard()->press('Enter');
        $page->keyboard()->press('Enter');
        // "choose one" is the label of the file input, which setInputFiles() fills as the picker would
        $page->locator('[data-test-content-upload-choose="de_DE"]')->setInputFiles(self::FIXTURES . '/cookie.jpg');
        $expected = sprintf("%s\n\n![cookie](/media/terms/%s)", $pasted, self::JPEG);
        expect($textarea)->toHaveValue($expected);

        self::changeAndWaitForRerender($page, 'document.querySelector(\'[data-test-content="de_DE"]\').blur()');
        $page->locator('[data-test-update-changes-button]')->click();
        expect($page->locator('[data-test-sylius-flash-message-type="success"]'))->toBeVisible();

        $terms = self::reloadTerms($terms);
        self::assertSame($expected, $terms->getTranslation('de_DE')->getContent());
        self::assertSame('The content of cookie_policy', $terms->getTranslation('en_US')->getContent());
        self::assertSame([], $page->pageErrors(), 'The Terms form threw JavaScript errors');
    }

    /**
     * The admin opens the Preview tab before the upload has finished. The hidden textarea can't take the focus, so its
     * placeholder is replaced without the editing commands, and the Live form re-renders the preview with the image
     */
    #[Test]
    public function an_upload_that_finishes_behind_the_preview_tab_shows_in_the_preview(): void
    {
        $terms = self::createTerms('cookie_policy');
        self::getEntityManager()->clear();

        $page = $this->open(sprintf('/admin/terms/%d/edit', (int) $terms->getId()));
        $textarea = $page->locator('[data-test-content="en_US"]');
        $textarea->fill('We bake cookies.');
        self::placeCursor($textarea, 0);

        // The upload is answered once this script has run, so the tab is open by then
        self::drop($page, 'en_US', [self::file('cookie.png')], 'document.querySelector(\'[data-test-content-preview-tab="en_US"]\').click()');

        $image = $page->locator('[data-test-content-preview="en_US"] img');
        expect($image)->toHaveAttribute('src', '/media/terms/' . self::PNG);
        expect($page->locator('[data-test-content-preview="en_US"]'))->toBeVisible();
        expect($textarea)->toBeHidden();
        expect($textarea)->toHaveValue(sprintf('![cookie](/media/terms/%s)We bake cookies.', self::PNG));
        self::assertSame([], $page->pageErrors(), 'The Terms form threw JavaScript errors');
    }

    /**
     * Each drop starts afresh, so only the latest error shows
     */
    #[Test]
    public function a_rejected_file_leaves_nothing_in_the_textarea_and_shows_an_error_that_names_it(): void
    {
        $terms = self::createTerms('cookie_policy');
        self::getEntityManager()->clear();
        $stored = self::getStoredImages();

        $page = $this->open(sprintf('/admin/terms/%d/edit', (int) $terms->getId()));
        $textarea = $page->locator('[data-test-content="en_US"]');
        $textarea->fill('We bake cookies.');

        $rejections = [
            [self::file('cookie.pdf'), "cookie.pdf isn't a PNG, JPEG, GIF or WebP image."],
            [self::file('cookie.svg'), "cookie.svg isn't a PNG, JPEG, GIF or WebP image."],
            [self::file('cookie.png', 'large.png', 6 * 1024 * 1024), 'large.png is larger than 5 MB.'],
        ];

        foreach ($rejections as [$file, $error]) {
            self::placeCursor($textarea, \strlen('We bake cookies.'));
            self::recordContentEdits($page, 'en_US');

            self::drop($page, 'en_US', [$file]);

            $page->waitForFunction('() => document.querySelectorAll(\'[data-test-content-upload-errors="en_US"] .invalid-feedback\').length > 0');
            self::assertSame([$error], self::getErrors($page, 'en_US'));
            expect($textarea)->toHaveValue('We bake cookies.');
            self::assertSame(
                [sprintf('We bake cookies.![Uploading %s…]()', $file['name']), 'We bake cookies.'],
                self::getRecordedContentEdits($page),
            );
        }

        // The errors are visible below the textarea
        expect($page->locator('[data-test-content-upload-errors="en_US"] .invalid-feedback'))->toBeVisible();
        self::assertSame($stored, self::getStoredImages());
        self::assertSame([], $page->pageErrors(), 'The Terms form threw JavaScript errors');
    }

    /**
     * Opens the page and waits until its scripts have set it up, so clicks reach Bootstrap and the Live components, and
     * the <file-attachment> elements are defined
     */
    private function open(string $path): PageInterface
    {
        $page = $this->visit($path);
        $page->waitForLoadState('networkidle');
        $page->waitForFunction('() => undefined !== customElements.get("file-attachment")');

        return $page;
    }

    /**
     * Runs the script, which changes a field of the Live form, and waits until the form has re-rendered
     */
    private static function changeAndWaitForRerender(PageInterface $page, string $script): void
    {
        $page->waitForResponse('**/_components/**', ['action' => $script]);
        $page->waitForFunction('() => null === document.querySelector("[data-controller~=live][busy]")');
    }

    private static function placeCursor(LocatorInterface $textarea, int $offset): void
    {
        $textarea->evaluate('(textarea, offset) => { textarea.focus(); textarea.setSelectionRange(offset, offset); }', $offset);
    }

    /**
     * A file to drop or paste: a fixture's bytes under a name, padded with zeros to the size
     *
     * @return array{name: string, type: string, base64: string, size: int}
     */
    private static function file(string $fixture, ?string $name = null, int $size = 0): array
    {
        $types = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'pdf' => 'application/pdf', 'svg' => 'image/svg+xml'];

        return [
            'name' => $name ?? $fixture,
            'type' => $types[pathinfo($fixture, \PATHINFO_EXTENSION)],
            'base64' => base64_encode((string) file_get_contents(self::FIXTURES . '/' . $fixture)),
            'size' => $size,
        ];
    }

    /**
     * Drops the files on the locale's textarea
     *
     * @param list<array{name: string, type: string, base64: string, size: int}> $files
     * @param string $then a script to run once the placeholders are in
     */
    private static function drop(PageInterface $page, string $localeCode, array $files, string $then = ''): void
    {
        $page->evaluate(
            sprintf('async ([localeCode, files]) => {
                const makeFile = %s;
                const textarea = document.querySelector(`[data-test-content="${localeCode}"]`);
                const dataTransfer = new DataTransfer();
                for (const file of files) {
                    dataTransfer.items.add(makeFile(file));
                }
                // The element accepts the files asynchronously, and the placeholders go in when it does
                const accepted = new Promise((resolve) => document.addEventListener("file-attachment-accepted", () => setTimeout(resolve), { once: true }));
                for (const type of ["dragenter", "dragover", "drop"]) {
                    textarea.dispatchEvent(new DragEvent(type, { bubbles: true, cancelable: true, dataTransfer }));
                }
                await accepted;
                %s;
            }', self::MAKE_FILE, $then),
            [$localeCode, $files],
        );
    }

    /**
     * Pastes the file into the locale's textarea, as when a screenshot is pasted
     *
     * @param array{name: string, type: string, base64: string, size: int} $file
     */
    private static function paste(PageInterface $page, string $localeCode, array $file): void
    {
        $page->evaluate(
            sprintf('([localeCode, file]) => {
                const makeFile = %s;
                const clipboardData = new DataTransfer();
                clipboardData.items.add(makeFile(file));
                document.querySelector(`[data-test-content="${localeCode}"]`).dispatchEvent(new ClipboardEvent("paste", { bubbles: true, cancelable: true, clipboardData }));
            }', self::MAKE_FILE),
            [$localeCode, $file],
        );
    }

    /**
     * Records the locale's Content after each input event, from now on
     */
    private static function recordContentEdits(PageInterface $page, string $localeCode): void
    {
        $page->evaluate(
            'localeCode => {
                window.setonoTermsContentEdits = [];
                if (undefined === window.setonoTermsRecorder) {
                    window.setonoTermsRecorder = (event) => {
                        if (event.target.matches(`[data-test-content="${localeCode}"]`)) {
                            window.setonoTermsContentEdits.push(event.target.value);
                        }
                    };
                    document.addEventListener("input", window.setonoTermsRecorder);
                }
            }',
            $localeCode,
        );
    }

    /**
     * The Content after each edit. Chrome fires an input event for each line a multi-line insertText inserts, each
     * with the whole edit done, so repeats of the same Content are left out
     *
     * @return list<string>
     */
    private static function getRecordedContentEdits(PageInterface $page): array
    {
        /** @var list<string> $edits */
        $edits = $page->evaluate('() => window.setonoTermsContentEdits.filter((value, index, values) => 0 === index || value !== values[index - 1])');

        return $edits;
    }

    /**
     * @return list<string>
     */
    private static function getErrors(PageInterface $page, string $localeCode): array
    {
        /** @var list<string> $errors */
        $errors = $page->locator(sprintf('[data-test-content-upload-errors="%s"] .invalid-feedback', $localeCode))->evaluateAll('elements => elements.map(element => element.textContent.trim())');

        return $errors;
    }
}
