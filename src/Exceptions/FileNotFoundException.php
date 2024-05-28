<?php

namespace Henrik\Filesystem\Exceptions;

use Throwable;

class FileNotFoundException extends FilesystemException
{
    public function __construct(string $file, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(sprintf('The file `%s` not found', $file), $code, $previous);
    }
}