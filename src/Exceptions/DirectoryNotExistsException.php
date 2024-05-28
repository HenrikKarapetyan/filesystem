<?php

namespace Henrik\Filesystem\Exceptions;

use Throwable;

class DirectoryNotExistsException extends FilesystemException
{
    public function __construct(string $directory, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(sprintf('The directory `%s` not exists!', $directory), $code, $previous);
    }
}