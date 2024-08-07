<?php

namespace Henrik\Filesystem;

use FilesystemIterator;
use Henrik\Contracts\Filesystem\FilesystemInterface;
use Henrik\Filesystem\Exceptions\DirectoryNotExistsException;
use Henrik\Filesystem\Exceptions\FileNotFoundException;
use Henrik\Filesystem\Exceptions\FilesystemException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class Filesystem implements FilesystemInterface
{
    /**
     * {@inheritDoc}
     */
    public static function mkdir(string $path, int $mode = 0o775): void
    {
        if (!is_dir($path)) {

            $parent_path = dirname($path);

            if (!file_exists($parent_path)) {
                self::mkdir($parent_path); // recursively create parent dirs first
            }

            mkdir($path);
            chmod($path, $mode);

        }
    }

    /**
     * {@inheritDoc}
     */
    public static function getFilesFromDirectory(string $directory, ?string $fileExtension, ?array $excludedPaths = []): array
    {
        return self::walkFromDirectories($directory, $fileExtension, $excludedPaths, function (string $directory, SplFileInfo $file) {

            return $file->getRealPath();
        });
    }

    /**
     * {@inheritDoc}
     */
    public static function getPhpClassesFromDirectory(string $directory, string $namespace, ?array $excludedPaths = []): array
    {

        return self::walkFromDirectories($directory, 'php', $excludedPaths, function (string $directory, SplFileInfo $file) use ($namespace) { // @phpstan-ignore-line

            $className = $file->getBasename('.' . $file->getExtension());

            if ($file->isFile()) {
                $filePath = str_replace('/', '\\', str_replace($directory, '', $file->getPath()));
                $class    = sprintf('%s%s\\%s', $namespace, $filePath, $className);

                if (class_exists($class)) {
                    return $class;
                }

                return null;
            }

            return null;
        });
    }

    /**
     * {@inheritDoc}
     */
    public static function createFile(string $path, int $mode = 0o664, ?string $content = null): void
    {
        if (!file_exists($path)) {
            $parent_path = dirname($path);
            self::mkdir($parent_path);
            file_put_contents($path, $content, $mode);
        }
    }

    /**
     * {@inheritDoc}
     */
    public static function deleteDirectory(string $directory): void
    {
        self::walkFromDirectories(
            $directory,
            null,
            null,
            function (
                string $directory,
                SplFileInfo $file
            ) {
                $todo = ($file->isDir() ? 'rmdir' : 'unlink');
                $todo($file->getRealPath());
            },
            RecursiveIteratorIterator::CHILD_FIRST
        );
        rmdir($directory);
    }

    /**
     * {@inheritDoc}
     */
    public static function deleteFile(string $file): void
    {

        self::checkFileExists($file);
        unlink($file);
    }

    /**
     * {@inheritDoc}
     */
    public static function copyFile(string $source, string $destination): void
    {
        self::checkFileExists($source);
        copy($source, $destination);
    }

    /**
     * {@inheritDoc}
     */
    public static function moveFile(string $source, string $destination): void
    {
        self::checkFileExists($source);
        rename($source, $destination);
    }

    /**
     * {@inheritDoc}
     */
    public static function copyDirectory(string $source, string $destination, ?string $fileExtension = null, ?array $excludedPaths = []): void
    {
        self::checkDirectoryExists($source);
        self::walkFromDirectories(
            $source,
            $fileExtension,
            $excludedPaths,
            function (string $directory, SplFileInfo $file) use ($destination) {

                $destinationBaseDir = str_replace($directory, '', $file->getPath());
                $destinationDir     = sprintf('%s%s', $destination, $destinationBaseDir);

                if (!is_dir($destinationDir)) {
                    self::mkdir($destinationDir);
                }

                copy($file->getRealPath(), sprintf('%s%s%s', $destinationDir, DIRECTORY_SEPARATOR, $file->getBasename()));

            },
            RecursiveIteratorIterator::LEAVES_ONLY
        );
    }

    /**
     * {@inheritDoc}
     */
    public static function moveDirectory(string $source, string $destination, ?string $fileExtension = null, ?array $excludedPaths = []): void
    {
        self::copyDirectory($source, $destination, $fileExtension, $excludedPaths);
        self::deleteDirectory($source);
    }

    public static function getPhpSourcesFromDirectory(string $directory, string $namespace, ?array $excludedPaths = []): array
    {
        return self::walkFromDirectories($directory, 'php', $excludedPaths, function (string $directory, SplFileInfo $file) use ($namespace) { // @phpstan-ignore-line

            $className = $file->getBasename('.' . $file->getExtension());

            if ($file->isFile()) {
                $filePath = str_replace('/', '\\', str_replace($directory, '', $file->getPath()));

                return sprintf('%s%s\\%s', $namespace, $filePath, $className);

            }

            return null;
        });
    }

    /**
     * @param string $path
     *
     * @throws FilesystemException
     */
    private static function checkDirectoryExists(string $path): void
    {
        if (!is_dir($path)) {
            throw new DirectoryNotExistsException($path);
        }
    }

    /**
     * @param string $file
     *
     * @throws FileNotFoundException
     */
    private static function checkFileExists(string $file): void
    {
        if (!file_exists($file)) {
            throw new FileNotFoundException($file);
        }
    }

    /**
     * @param string        $directory
     * @param string|null   $fileExtension
     * @param string[]|null $excludedPaths
     * @param callable|null $callback
     * @param int           $mode
     *
     * @return string[]
     */
    private static function walkFromDirectories(string $directory, ?string $fileExtension = null, ?array $excludedPaths = [], ?callable $callback = null, int $mode = RecursiveIteratorIterator::SELF_FIRST): array
    {
        $files    = [];
        $iterator = new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS);

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator($iterator, $mode) as $file) {

            if (is_array($excludedPaths) && in_array(needle: $iterator->getPathname(), haystack: $excludedPaths)) {
                continue;
            }

            /**
             * if  extension is set
             * otherwise we're just escaping any condition.
             *
             * TODO need to refactor this part of the code
             */
            if ($fileExtension && $fileExtension == $file->getExtension()) {

                if ($callback) {
                    $filteredFile = $callback($directory, $file);

                    if ($filteredFile) {
                        $files[] = $filteredFile;
                    }

                    continue;
                }
                $files[] = $file->getRealPath();

                continue;
            }

            if ($callback) {
                $filteredFile = $callback($directory, $file);

                if ($filteredFile) {
                    $files[] = $filteredFile;
                }

                continue;
            }

            $files[] = $file->getRealPath();

        }

        return $files;
    }
}