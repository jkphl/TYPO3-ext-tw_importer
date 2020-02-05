<?php

/**
 * Fischer Automobile
 *
 * @category Jkphl
 * @package Jkphl\Rdfalite
 * @subpackage Tollwerk\TwImporter\Utility
 * @author Joschi Kuphal <joschi@tollwerk.de> / @jkphl
 * @copyright Copyright © 2017 Joschi Kuphal <joschi@tollwerk.de> / @jkphl
 * @license http://opensource.org/licenses/MIT The MIT License (MIT)
 */

/***********************************************************************************
 *  The MIT License (MIT)
 *
 *  Copyright © 2017 Joschi Kuphal <joschi@kuphal.net> / @jkphl
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy of
 *  this software and associated documentation files (the "Software"), to deal in
 *  the Software without restriction, including without limitation the rights to
 *  use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
 *  the Software, and to permit persons to whom the Software is furnished to do so,
 *  subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
 *  FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
 *  COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
 *  IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
 *  CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 ***********************************************************************************/

namespace Tollwerk\TwImporter\Utility;

/**
 * Logger interface
 */
interface LoggerInterface
{
    /**
     * States
     */
    const STAGE_IDLE = 0;
    const STAGE_PREPARATION = 1;
    const STAGE_IMPORTING = 2;
    const STAGE_FINALIZING = 3;
    const STAGE_FINISHED = 4;
    const STAGE_ERROR = 5;

    /**
     * Log a message
     *
     * @param string $message Message
     * @param int $severity Message severity
     */
    public function log(string $message, int $severity);

    /**
     * Set the current import stage
     *
     * @param int $stage Import stage
     *
     * @return void
     */
    public function stage(int $stage);

    /**
     * Set the total number of records
     *
     * @param int $count Number of records
     *
     * @return void
     */
    public function count(int $count);

    /**
     * Set the current record index
     *
     * @param int $step Current record index
     *
     * @return void
     */
    public function step(int $step);
}
