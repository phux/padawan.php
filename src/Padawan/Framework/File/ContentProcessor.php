<?php

namespace Padawan\Framework\File;

use Padawan\Parser\Parser;
use Padawan\Parser\Walker\IndexGeneratingWalker;
use Padawan\Domain\Generator\IndexGenerator;
use Padawan\Parser\Walker\ScopeWalker;
use Padawan\Domain\Project;
use Padawan\Domain\Project\File;
use Padawan\Domain\Project\Index;

class ContentProcessor
{
    /**
     * @param Parser                $parser
     * @param IndexGeneratingWalker $walker
     * @param IndexGenerator        $indexGenerator
     * @param ScopeWalker           $scopeWalker
     */
    public function __construct(
        Parser $parser,
        IndexGeneratingWalker $walker,
        IndexGenerator $indexGenerator,
        ScopeWalker $scopeWalker
    ) {
        $this->parser = $parser;
        $this->walker = $walker;
        $this->indexGenerator = $indexGenerator;
        $this->scopeWalker = $scopeWalker;
    }

    /**
     * @param Project      $project
     * @param array|string $lines
     * @param int          $line
     * @param string       $filePath
     *
     * @return Scope|null
     */
    public function processFileContent(Project $project, $lines, $line, $filePath)
    {
        $content = $lines;
        if (is_array($lines)) {
            $content = implode("\n", $lines);
        }
        if (empty($content)) {
            return;
        }
        if (!array_key_exists($filePath, $this->cachePool)) {
            $this->cachePool[$filePath] = [0, [], []];
        }
        if ($this->isValidCache($filePath, $content)) {
            list(, , $scope) = $this->cachePool[$filePath];
            if ($scope) {
                return $scope;
            }
        }
        $index = $project->getIndex();
        $file = $index->findFileByPath($filePath);

        if (empty($file)) {
            $file = new File($filePath);
        }

        return $this->parseFile($index, $filePath, $content, $file, $line);
    }

    private function parseFile(Index $index, $filePath, $content, $file, $line)
    {
        $contentHash = hash('sha1', $content);
        $parser = $this->parser;
        $parser->addWalker($this->walker);
        $parser->setIndex($index);
        $fileScope = $parser->parseContent($filePath, $content);
        $this->indexGenerator->processFileScope(
            $file,
            $index,
            $fileScope,
            $contentHash
        );
        /** @var \Padawan\Domain\Project\Node\Uses */
        $uses = $parser->getUses();
        $this->scopeWalker->setLine($line);
        $parser->addWalker($this->scopeWalker);
        $parser->setIndex($index);
        $scope = $parser->parseContent($filePath, $content, $uses);
        $this->cachePool[$filePath] = [$contentHash, $fileScope, $scope];

        return $scope;
    }

    /**
     * @param string $file
     * @param string $content
     *
     * @return bool
     */
    private function isValidCache($file, $content)
    {
        $contentHash = hash('sha1', $content);
        list($hash) = $this->cachePool[$file];

        return $hash === $contentHash;
    }

    /** @property ScopeWalker */
    private $scopeWalker;
    private $cachePool = [];
    /** @property IndexGeneratingWalker */
    private $walker;
    /** @property IndexGenerator */
    private $indexGenerator;
}
