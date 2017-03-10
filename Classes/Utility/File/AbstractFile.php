<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 Joschi Kuphal <joschi@tollwerk.de>, tollwerk GmbH
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

namespace Tollwerk\TwImporter\Utility\File;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Abstract file
 */
abstract class AbstractFile implements FileInterface
{
    /**
     * Adapter configuration
     *
     * @var array
     */
    protected $config;

    /**
     * Set the adapter configuration
     *
     * @param array $config Adapter configuration
     */
    public function setConfig(array $config)
    {
        $this->config = $config;
    }
    /**
     * Expand and validate a directory path
     *
     * @param \string $directory Directory path
     * @throws \InvalidArgumentException    When the given path is not a valid directory
     * @return \string                        Expanded and validated (absolute) directory path
     */
    public function validateDirectory($directory)
    {
        $directory = trim(trim($directory, DIRECTORY_SEPARATOR));
        if (!strlen($directory)) {
            throw new \InvalidArgumentException('Empty directory name is not allowed');
        }
        $directory = GeneralUtility::getFileAbsFileName($directory);
        if (!@is_dir($directory)) {
            throw new \InvalidArgumentException(sprintf('Invalid directory "%s" (does not exist)', $directory));
        }
        if (!@is_writable($directory)) {
            throw new \InvalidArgumentException(sprintf('Invalid directory "%s" (is not writable)', $directory));
        }
        return $directory;
    }

}
