<?php

namespace Henrik\Filesystem\Exceptions;

use Exception;
use Henrik\Contracts\Filesystem\FileSystemExceptionInterface;

class FilesystemException extends Exception implements FileSystemExceptionInterface {}