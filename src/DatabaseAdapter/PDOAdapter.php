<?php

/*
 * CKFinder
 * ========
 * https://ckeditor.com/ckfinder/
 * Copyright (c) 2007-2022, CKSource Holding sp. z o.o. All rights reserved.
 *
 * The software, this file and its contents are subject to the CKFinder
 * License. Please read the license.txt file before using, installing, copying,
 * modifying or distribute this file or part of its contents. The contents of
 * this file is part of the Source Code of CKFinder.
 */

namespace CKSource\CKFinder\Plugin\DatabaseAdapter;

use CKSource\CKFinder\Exception\InvalidConfigException;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use League\Flysystem\PathPrefixer;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use LogicException;
use PDO;

/**
 * The PDOAdapter class.
 *
 * The Flysystem PDO Database adapter.
 */
class PDOAdapter implements FilesystemAdapter
{
    /**
     * @var PDO
     */
    protected $pdo;

    /**
     * @var string
     */
    protected $table;

    protected FinfoMimeTypeDetector $mimeTypeDetector;

    protected PathPrefixer $pathPrefixer;

    /**
     * The PDOAdapter constructor.
     */
    public function __construct(PDO $pdo, string $tableName)
    {
        $this->pdo = $pdo;

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $tableName)) {
            throw new \InvalidArgumentException('Invalid table name');
        }

        $this->table = $tableName;

        $this->mimeTypeDetector = new FinfoMimeTypeDetector();

        $this->pathPrefixer = new PathPrefixer('/');
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidConfigException
     */
    public function write($path, $contents, Config $config): void
    {
        $query = $this->pdo->prepare(
            "
            INSERT INTO {$this->table} (path, contents, size, type, mimetype, timestamp)
            VALUES(:path, :contents, :size, :type, :mimetype, :timestamp)
            "
        );

        $size = \strlen($contents);
        $type = 'file';
        $mimetype = $this->mimeTypeDetector->detectMimeType($path, $contents);
        $timestamp = time();

        $query->bindParam(':path', $path, PDO::PARAM_STR);
        $query->bindParam(':contents', $contents, PDO::PARAM_LOB);
        $query->bindParam(':size', $size, PDO::PARAM_INT);
        $query->bindParam(':type', $type, PDO::PARAM_STR);
        $query->bindParam(':mimetype', $mimetype, PDO::PARAM_STR);
        $query->bindParam(':timestamp', $timestamp, PDO::PARAM_INT);

        try {
            $query->execute();
        } catch (\PDOException $exception) {
            throw new InvalidConfigException('Query executing failed', [$exception->getMessage()], previous: $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function writeStream($path, $contents, Config $config): void
    {
        $this->write($path, stream_get_contents($contents), $config);
    }

    /**
     * {@inheritdoc}
     */
    public function update($path, $contents, Config $config)
    {
        $query = $this->pdo->prepare(
            "
            UPDATE {$this->table}
            SET contents=:newcontents, mimetype=:mimetype, size=:size
            WHERE path=:path
            "
        );

        $size = \strlen($contents);
        $mimetype = $this->mimeTypeDetector->detectMimeType($path, $contents);

        $query->bindParam(':size', $size, PDO::PARAM_INT);
        $query->bindParam(':mimetype', $mimetype, PDO::PARAM_STR);
        $query->bindParam(':newcontents', $contents, PDO::PARAM_LOB);
        $query->bindParam(':path', $path, PDO::PARAM_STR);

        return $query->execute() ? compact('path', 'contents', 'size', 'mimetype') : false;
    }

    /**
     * {@inheritdoc}
     */
    public function updateStream($path, $resource, Config $config): bool|array
    {
        return $this->update($path, stream_get_contents($resource), $config);
    }

    /**
     * {@inheritdoc}
     */
    public function rename($path, $newpath): bool
    {
        $query = $this->pdo->prepare(
            "
            SELECT type
            FROM {$this->table}
            WHERE path=:path
            "
        );

        $query->bindParam(':path', $path, PDO::PARAM_STR);

        if ($query->execute()) {
            $object = $query->fetch(PDO::FETCH_ASSOC);

            if ('dir' === $object['type']) {
                $dirContents = $this->listContents($path, true);

                $query = $this->pdo->prepare("UPDATE {$this->table} SET path=:newpath WHERE path=:path");

                $pathLength = \strlen($path);

                $query->bindParam(':path', $currentObjectPath, PDO::PARAM_STR);
                $query->bindParam(':newpath', $newObjectPath, PDO::PARAM_STR);

                foreach ($dirContents as $object) {
                    $currentObjectPath = $object['path'];
                    $newObjectPath = $newpath.substr($currentObjectPath, $pathLength);

                    $query->execute();
                }
            }
        }

        $query = $this->pdo->prepare(
            "
            UPDATE {$this->table}
            SET path=:newpath
            WHERE path=:path
            "
        );

        $query->bindParam(':path', $path, PDO::PARAM_STR);
        $query->bindParam(':newpath', $newpath, PDO::PARAM_STR);

        return $query->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function copy(string $path, string $newpath, Config $config): void
    {
        $query = $this->pdo->prepare(
            "
            SELECT *
            FROM {$this->table}
            WHERE path=:path
            "
        );

        $query->bindParam(':path', $path, PDO::PARAM_STR);

        if ($query->execute()) {
            $result = $query->fetch(PDO::FETCH_ASSOC);

            if (!empty($result)) {
                $query = $this->pdo->prepare(
                    "
                    INSERT INTO {$this->table} (path, contents, size, type, mimetype, timestamp)
                    VALUES(:path, :contents, :size, :type, :mimetype, :timestamp)
                    "
                );

                $query->bindParam(':path', $newpath, PDO::PARAM_STR);
                $query->bindParam(':contents', $result['contents'], PDO::PARAM_LOB);
                $query->bindParam(':size', $result['size'], PDO::PARAM_INT);
                $query->bindParam(':type', $result['type'], PDO::PARAM_STR);
                $query->bindParam(':mimetype', $result['mimetype'], PDO::PARAM_STR);
                $query->bindValue(':timestamp', time(), PDO::PARAM_INT);

                try {
                    $query->execute();
                } catch (\PDOException $exception) {
                    throw new InvalidConfigException('Query executing failed', [$exception->getMessage()], previous: $exception);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function move(string $path, string $newpath, Config $config): void
    {
        $this->copy($path, $newpath, $config);
        $this->delete($path);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($path): void
    {
        $query = $this->pdo->prepare(
            "
            DELETE FROM {$this->table}
            WHERE path=:path
            "
        );

        $query->bindParam(':path', $path, PDO::PARAM_STR);

        try {
            $query->execute();
        } catch (\PDOException $exception) {
            throw new InvalidConfigException('Query executing failed', [$exception->getMessage()], previous: $exception);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws FilesystemException
     */
    public function deleteDirectory(string $dirname): void
    {
        $dirContents = $this->listContents($dirname, true);

        if (!empty($dirContents)) {
            $query = $this->pdo->prepare(
                "
                DELETE FROM {$this->table}
                WHERE path=:path
                "
            );

            $query->bindParam(':path', $currentObjectPath, PDO::PARAM_STR);

            foreach ($dirContents as $object) {
                $currentObjectPath = $object['path'];
                $query->execute();
            }
        }

        $query = $this->pdo->prepare(
            "
            DELETE FROM {$this->table}
            WHERE path=:path
            AND type='dir'
            "
        );

        $query->bindParam(':path', $path, PDO::PARAM_STR);

        try {
            $query->execute();
        } catch (\PDOException $exception) {
            throw new InvalidConfigException('Query executing failed', [$exception->getMessage()], previous: $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createDirectory(string $dirname, Config $config): void
    {
        $query = $this->pdo->prepare(
            "
            INSERT INTO {$this->table} (path, type, timestamp)
            VALUES(:path, :type, :timestamp)
            "
        );

        $query->bindParam(':path', $dirname, PDO::PARAM_STR);
        $query->bindValue(':type', 'dir', PDO::PARAM_STR);
        $query->bindValue(':timestamp', time(), PDO::PARAM_STR);

        try {
            $query->execute();
        } catch (\PDOException $exception) {
            throw new InvalidConfigException('Query executing failed', [$exception->getMessage()], previous: $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function has($path): bool
    {
        $query = $this->pdo->prepare(
            "
            SELECT id
            FROM {$this->table}
            WHERE path=:path
            "
        );

        $query->bindParam(':path', $path, PDO::PARAM_STR);

        if ($query->execute()) {
            return (bool) $query->fetch(PDO::FETCH_ASSOC);
        }

        return false;
    }

    public function directoryExists(string $path): bool
    {
        $type = 'dir';

        $query = $this->pdo->prepare(
            "
            SELECT id
            FROM {$this->table}
            WHERE path=:path AND type=:type
            "
        );

        $query->bindParam(':path', $path, PDO::PARAM_STR);
        $query->bindParam(':type', $type, PDO::PARAM_STR);

        if ($query->execute()) {
            return (bool) $query->fetch(PDO::FETCH_ASSOC);
        }

        return false;
    }

    public function fileExists(string $path): bool
    {
        $type = 'file';

        $query = $this->pdo->prepare(
            "
            SELECT id
            FROM {$this->table}
            WHERE path=:path AND type=:type
            "
        );

        $query->bindParam(':path', $path, PDO::PARAM_STR);
        $query->bindParam(':type', $type, PDO::PARAM_STR);

        if ($query->execute()) {
            return (bool) $query->fetch(PDO::FETCH_ASSOC);
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function read(string $path): string
    {
        $query = $this->pdo->prepare(
            "
            SELECT contents
            FROM {$this->table}
            WHERE path=:path
            "
        );

        $query->bindParam(':path', $path, PDO::PARAM_STR);

        if ($query->execute()) {
            return $query->fetch(PDO::FETCH_ASSOC)['contents'];
        }

        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function readStream($path)
    {
        $stream = fopen('php://temp', 'w+');
        $result = $this->read($path);

        if (!$result) {
            fclose($stream);

            return false;
        }

        fwrite($stream, $result);
        rewind($stream);

        return $stream;
    }

    /**
     * {@inheritdoc}
     */
    public function listContents($directory = '', $recursive = false): iterable
    {
        $query = "SELECT path, size, type, mimetype, timestamp FROM {$this->table}";

        $useWhere = (bool) \strlen($directory);

        if ($useWhere) {
            $query .= ' WHERE path LIKE :path_prefix';
        }

        $query = $this->pdo->prepare($query);

        if ($useWhere) {
            $pathPrefix = $directory.'/%';
            $query->bindParam(':path_prefix', $pathPrefix, PDO::PARAM_STR);
        }

        $result = $query->execute() ? $query->fetchAll(PDO::FETCH_ASSOC) : [];

        // Level of directory you open in UI layer
        $directoryLevel = substr_count($directory, '/') + 1;

        // Loop that deletes all records from database that aren't children of opened directory in UI layer
        foreach ($result as $key => $record) {
            if (substr_count($record['path'], '/') > $directoryLevel) {
                unset($result[$key]);
            }
        }

        foreach ($result as $content) {
            if ('dir' === $content['type']) {
                yield new DirectoryAttributes(
                    $content['path'],
                    lastModified: $content['timestamp'],
                );
            }

            if ('file' === $content['type']) {
                yield new FileAttributes(
                    $content['path'],
                    $content['size'] ?? null,
                    lastModified: $content['timestamp'],
                    mimeType: $content['mimeType'] ?? null,
                );
            }
        }

        return [];
    }

    /**
     * Get all the metadata of a file or a directory.
     */
    public function getMetadata(string $path): FileAttributes
    {
        $query = $this->pdo->prepare(
            "
            SELECT id, path, size, type, mimetype, timestamp
            FROM {$this->table}
            WHERE path=:path
            "
        );

        $query->bindParam(':path', $path, PDO::PARAM_STR);

        try {
            $query->execute();
        } catch (\PDOException $exception) {
            throw new InvalidConfigException('Query executing failed', [$exception->getMessage()], previous: $exception);
        }

        $result = $query->fetch(PDO::FETCH_ASSOC);

        return new FileAttributes(
            $result['path'],
            $result['size'],
            $result['visibility'] ?? null,
            $result['timestamp'],
            $result['mimetype']
        );
    }

    /**
     * Get all the metadata of a file or a directory.
     *
     * @throws InvalidConfigException
     */
    public function fileSize(string $path): FileAttributes
    {
        return $this->getMetadata($path);
    }

    /**
     * Get the MIME type of file.
     *
     * @throws InvalidConfigException
     */
    public function mimeType(string $path): FileAttributes
    {
        return $this->getMetadata($path);
    }

    /**
     * Get the timestamp of file.
     *
     * @throws InvalidConfigException
     */
    public function lastModified(string $path): FileAttributes
    {
        return $this->getMetadata($path);
    }

    /**
     * Get the visibility of file.
     *
     * @throws InvalidConfigException
     */
    public function visibility(string $path): FileAttributes
    {
        return $this->getMetadata($path);
    }

    /**
     * Get the visibility of a file.
     *
     * @throws LogicException
     */
    public function getVisibility(string $path)
    {
        throw new LogicException(static::class.' does not support visibility. Path: '.$path);
    }

    /**
     * Set the visibility for a file.
     *
     * @throws LogicException
     */
    public function setVisibility(string $path, string $visibility): void
    {
        throw new LogicException(static::class.' does not support visibility. Path: '.$path.', visibility: '.$visibility);
    }
}
