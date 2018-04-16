<?php


namespace rollun\uploader;


/**
 * Interface SeekableIterator
 * @package rollun\uploader
 */
interface SeekableIterator extends \SeekableIterator
{
    /**
     * Seeks to a position
     * @link TODO: add link to doc
     * @param mixed $position <p>
     * The position to seek to.
     * </p>
     * @return void
     * @since 5.1.0
     */
    public function seek($position);
}