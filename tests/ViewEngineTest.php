<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use rguezque\Exception\FileNotFoundException;
use rguezque\Exception\NotFoundException;
use rguezque\ViewEngine;

final class ViewEngineTest extends TestCase
{
    private string $root;
    private string $templates;
    private string $adminTemplates;
    private string $assetDirectory;
    private array $files = [];

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katya-views-' . bin2hex(random_bytes(6));
        $this->templates = $this->root . '/templates';
        $this->adminTemplates = $this->root . '/admin';
        $this->assetDirectory = $this->root . '/assets';
        mkdir($this->templates, 0755, true);
        mkdir($this->adminTemplates, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        foreach ([$this->assetDirectory, $this->adminTemplates, $this->templates, $this->root] as $directory) {
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    public function testFetchRendersWithSharedAndPerViewArguments(): void
    {
        $this->write($this->templates . '/welcome.view.php', 'Hello <?= $name ?>:<?= $shared ?>');
        $views = (new ViewEngine($this->templates))
            ->addArguments(['name' => 'default', 'shared' => 'shared']);

        self::assertSame('Hello Ada:shared', $views->fetch('welcome', ['name' => 'Ada']));
    }

    public function testNamespacesFragmentsArgumentsEscapingAndInsert(): void
    {
        $this->write($this->adminTemplates . '/title.view.php', '<h1><?= $title ?></h1>');
        $this->write($this->templates . '/page.view.php', '<?= $heading ?>|<?= $this->e($unsafe) ?>');
        $views = new ViewEngine($this->templates, ['admin' => $this->adminTemplates]);

        self::assertSame('<h1>Panel</h1>', $views->fetch('admin::title', ['title' => 'Panel']));
        $views->fetchFragment('admin::title', 'heading', ['title' => 'Fragment']);
        self::assertSame('<h1>Fragment</h1>|&lt;script&gt;', $views->fetch('page', ['unsafe' => '<script>']));
        self::assertSame('', $views->e(null));

        $this->write($this->templates . '/partial.view.php', 'partial <?= $value ?>');
        ob_start();
        $views->insert('partial', ['value' => 'content']);
        $inserted = ob_get_clean();
        self::assertSame('partial content', $inserted);
    }

    public function testArgumentCollectionsAndNamespaceValidation(): void
    {
        $this->write($this->templates . '/current.view.php', '<?= $value ?>');
        $views = (new ViewEngine($this->templates))
            ->addArgument(' value ', 'first')
            ->addArguments(['second' => 2])
            ->setArguments(['value' => 'replaced']);

        self::assertSame('replaced', $views->fetch('current'));

        try {
            $views->addArguments(['not', 'associative']);
            self::fail('List arguments must be rejected.');
        } catch (InvalidArgumentException) {
            self::assertTrue(true);
        }

        try {
            $views->fetch('missing::view');
            self::fail('Unknown namespaces must be rejected.');
        } catch (InvalidArgumentException) {
            self::assertTrue(true);
        }

        $this->expectException(FileNotFoundException::class);
        $views->fetch('does-not-exist');
    }

    public function testAssetBuildsVersionedRootRelativeUrlAndRejectsMissingFiles(): void
    {
        $asset = $this->assetDirectory . '/app.css';
        mkdir($this->assetDirectory);
        $this->write($asset, 'body{}');
        $oldDocumentRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;
        $_SERVER['DOCUMENT_ROOT'] = $this->root;

        try {
            $view = new ViewEngine($this->templates);
            self::assertSame(
                '/assets/app.css?theme=dark&v=' . filemtime($asset) . '#main',
                $view->asset('/assets/app.css?theme=dark#main')
            );

            $this->expectException(FileNotFoundException::class);
            $view->asset('/assets/missing.css');
        } finally {
            if ($oldDocumentRoot === null) {
                unset($_SERVER['DOCUMENT_ROOT']);
            } else {
                $_SERVER['DOCUMENT_ROOT'] = $oldDocumentRoot;
            }
        }
    }

    public function testConstructorRejectsMissingTemplateDirectories(): void
    {
        $this->expectException(NotFoundException::class);
        new ViewEngine($this->root . '/absent');
    }

    private function write(string $path, string $contents): void
    {
        file_put_contents($path, $contents);
        $this->files[] = $path;
    }
}
