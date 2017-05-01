<?php

namespace Padawan\Framework\Domain\Project;

class CachePool
{
    /**
     * @var array
     */
    private $items = [];

    /**
     * @param string $filePath
     * @param string $content
     *
     * @return bool
     */
    public function isValid($filePath, $content)
    {
        if (!isset($this->items[$file])) {
            return false;
        }
        list($hash) = $this->items[$file];

        return $hash === sha1($content);
    }

    /**
     * @param string $filePath
     * @param string $content
     * @param mixed  $fileScope
     * @param mixed  $scope
     */
    public function set($filePath, $content, $fileScope, $scope)
    {
        $this->items[$filePath] = [sha1($content), $fileScope, $scope];
    }

    /**
     * @param string $filePath
     *
     * @return array|null
     */
    public function get($filePath)
    {
        if (isset($this->items[$filePath])) {
            return $this->items[$filePath];
        }
    }
}
