<?php namespace Kortdev\DotenvEditor;

use Illuminate\Contracts\Container\Container;
use Kortdev\DotenvEditor\Exceptions\KeyNotFoundException;

/**
 * The DotenvEditor class.
 *
 * @package Kortdev\DotenvEditor
 * @author Lars Kort <lars@kort.dev>
 */
class DotenvEditor
{
    /**
     * The IoC Container
     *
     * @var \Illuminate\Container\Container
     */
    protected $app;

    /**
     * Store instance of Config Repository;
     *
     * @var \Illuminate\Config\Repository
     */
    protected $config;

    /**
     * The formatter instance
     *
     * @var \Kortdev\DotenvEditor\DotenvFormatter
     */
    protected $formatter;

    /**
     * The reader instance
     *
     * @var \Kortdev\DotenvEditor\DotenvReader
     */
    protected $reader;

    /**
     * The writer instance
     *
     * @var \Kortdev\DotenvEditor\DotenvWriter
     */
    protected $writer;

    /**
     * The file path
     *
     * @var string
     */
    protected $filePath;


    /**
     * Create a new DotenvEditor instance
     *
     * @param  \Illuminate\Contracts\Config\Repository    $config
     *
     * @return void
     */
    public function __construct()
    {
        //  $this->app        = $app;
        $this->formatter = new DotenvFormatter;
        $this->reader = new DotenvReader($this->formatter);
        $this->writer = new DotenvWriter($this->formatter);
        $this->load();
    }

    /**
     * Load file for working
     *
     * @param  string|null  $filePath           The file path
     * @param  bool      $restoreIfNotFound  Restore this file from other file if it's not found
     * @param  string|null  $restorePath        The file path you want to restore from
     *
     * @return DotenvEditor
     */
    public function load($filePath = null)
    {
        $this->resetContent();

        if (! is_null($filePath)) {
            $this->filePath = $filePath;
        } else {
            $this->filePath = __DIR__.'/../../../../../../.env';
        }

        $this->reader->load($this->filePath);

        if (file_exists($this->filePath)) {
            $this->writer->setBuffer($this->getContent());

            return $this;
        }


        return $this;
    }

    /**
     * Reset content for editor
     *
     * @return void
     */
    protected function resetContent()
    {
        $this->filePath = null;

        $this->reader->load(null);
        $this->writer->setBuffer(null);
    }



    /*
    |--------------------------------------------------------------------------
    | Working with reading
    |--------------------------------------------------------------------------
    |
    | getContent($content)
    | getLines()
    | getKeys()
    | keyExists($key)
    | getValue($key)
    |
    */

    /**
     * Get raw content of file
     *
     * @return string
     */
    public function getContent()
    {
        return $this->reader->content();
    }

    /**
     * Get all lines from file
     *
     * @return array
     */
    public function getLines()
    {
        return $this->reader->lines();
    }

    /**
     * Get all or exists given keys in file content
     *
     * @param  array  $keys
     *
     * @return array
     */
    public function getKeys($keys = [])
    {
        $allKeys = $this->reader->keys();

        return array_filter($allKeys, function ($key) use ($keys) {
            if (! empty($keys)) {
                return in_array($key, $keys);
            }

            return true;
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Check, if a given key is exists in the file content
     *
     * @param  string  $keys
     *
     * @return bool
     */
    public function keyExists($key)
    {
        $allKeys = $this->getKeys();

        return array_key_exists($key, $allKeys);
    }

    /**
     * Return the value matching to a given key in the file content
     *
     * @param  $key
     *
     * @throws \Kortdev\DotenvEditor\Exceptions\KeyNotFoundException
     *
     * @return string
     */
    public function getValue($key)
    {
        $allKeys = $this->getKeys([$key]);

        if (array_key_exists($key, $allKeys)) {
            return $allKeys[$key]['value'];
        }

        throw new KeyNotFoundException('Requested key not found in your file.');
    }

    /*
    |--------------------------------------------------------------------------
    | Working with writing
    |--------------------------------------------------------------------------
    |
    | getBuffer()
    | addEmpty()
    | addComment($comment)
    | setKeys($data)
    | setKey($key, $value = null, $comment = null, $export = false)
    | deleteKeys($keys = [])
    | deleteKey($key)
    | save()
    |
    */

    /**
     * Return content in buffer
     *
     * @return string
     */
    public function getBuffer()
    {
        return $this->writer->getBuffer();
    }

    /**
     * Add empty line to buffer
     *
     * @return DotenvEditor
     */
    public function addEmpty()
    {
        $this->writer->appendEmptyLine();

        return $this;
    }

    /**
     * Add comment line to buffer
     *
     * @param object
     *
     * @return DotenvEditor
     */
    public function addComment($comment)
    {
        $this->writer->appendCommentLine($comment);

        return $this;
    }

    /**
     * Set many keys to buffer
     *
     * @param  array  $data
     *
     * @return DotenvEditor
     */
    public function setKeys($data)
    {
        foreach ($data as $i => $setter) {
            if (! is_array($setter)) {
                if (! is_string($i)) {
                    continue;
                }

                $setter = [
                    'key' => $i,
                    'value' => $setter,
                ];
            }

            if (array_key_exists('key', $setter)) {
                $key = $this->formatter->formatKey($setter['key']);
                $value = array_key_exists('value', $setter) ? $setter['value'] : null;
                $comment = array_key_exists('comment', $setter) ? $setter['comment'] : null;
                $export = array_key_exists('export', $setter) ? $setter['export'] : false;

                if (! is_file($this->filePath) || ! $this->keyExists($key)) {
                    $this->writer->appendSetter($key, $value, $comment, $export);
                } else {
                    $oldInfo = $this->getKeys([$key]);
                    $comment = is_null($comment) ? $oldInfo[$key]['comment'] : $comment;

                    $this->writer->updateSetter($key, $value, $comment, $export);
                }
            }
        }

        return $this;
    }

    /**
     * Set one key to buffer
     *
     * @param string       $key      Key name of setter
     * @param string|null  $value    Value of setter
     * @param string|null  $comment  Comment of setter
     * @param bool      $export   Leading key name by "export "
     *
     * @return DotenvEditor
     */
    public function setKey($key, $value = null, $comment = null, $export = false)
    {
        $data = [compact('key', 'value', 'comment', 'export')];

        return $this->setKeys($data);
    }

    /**
     * Delete many keys in buffer
     *
     * @param  array $keys
     *
     * @return DotenvEditor
     */
    public function deleteKeys($keys = [])
    {
        foreach ($keys as $key) {
            $this->writer->deleteSetter($key);
        }

        return $this;
    }

    /**
     * Delete on key in buffer
     *
     * @param  string  $key
     *
     * @return DotenvEditor
     */
    public function deleteKey($key)
    {
        $keys = [$key];

        return $this->deleteKeys($keys);
    }

    /**
     * Save buffer to file
     *
     * @return DotenvEditor
     */
    public function save()
    {
        $this->writer->save($this->filePath);

        return $this;
    }
}
